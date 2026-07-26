#!/usr/bin/env sh
set -eu

IMAGE_REF="${1:?immutable image reference is required}"
NAMESPACE="${KUBE_NAMESPACE:-pesantenku}"
DEPLOYMENT="${CANARY_DEPLOYMENT:-pesantenku-canary}"
MIGRATION_SECRET_NAME="${MIGRATION_ENV_SECRET:-${PRODUCTION_ENV_SECRET:-pesantenku-migration-env}}"
PROMETHEUS="${PROMETHEUS_URL:?PROMETHEUS_URL is required}"
PROMETHEUS_TOKEN="${PROMETHEUS_BEARER_TOKEN:?PROMETHEUS_BEARER_TOKEN is required}"
COSIGN_IDENTITY="${COSIGN_CERTIFICATE_IDENTITY:?COSIGN_CERTIFICATE_IDENTITY is required}"
COSIGN_ISSUER="${COSIGN_CERTIFICATE_OIDC_ISSUER:-https://token.actions.githubusercontent.com}"
CANARY_HEALTH_HOST="${CANARY_HEALTH_HOST:-pesantrenku.ai}"
OBSERVATION_SECONDS="${CANARY_OBSERVATION_SECONDS:-120}"
MIN_REQUESTS="${CANARY_MIN_REQUESTS:-20}"
MAX_ERROR_RATIO="${CANARY_MAX_ERROR_RATIO:-0.01}"
IMAGE_REPOSITORY=${IMAGE_REF%@*}
DIGEST_SUFFIX=$(printf '%s' "$IMAGE_REF" | sha256sum | cut -c1-12)
PROM_SELECTOR="${CANARY_PROMETHEUS_SELECTOR:-track=\"canary\",revision=\"${DIGEST_SUFFIX}\"}"
MIGRATION_JOB="${DEPLOYMENT}-migration-${DIGEST_SUFFIX}"
MIGRATION_TIMEOUT_SECONDS="${MIGRATION_TIMEOUT_SECONDS:-600}"
DEPLOYMENT_UPDATED=false
MIGRATION_ACTIVE=false
STATE_DIR=$(mktemp -d)
PREVIOUS_DEPLOYMENT_FILE="$STATE_DIR/previous-deployment.json"
ROLLOUT_PATCH_FILE="$STATE_DIR/rollout-patch.json"
ROLLBACK_PATCH_FILE="$STATE_DIR/rollback-patch.json"
LIVE_DEPLOYMENT_FILE="$STATE_DIR/live-deployment.json"
LIVE_PODS_FILE="$STATE_DIR/live-pods.json"

case "$IMAGE_REF" in
  *@sha256:????????????????????????????????????????????????????????????????) ;;
  *) echo "Canary image must use an immutable sha256 digest: $IMAGE_REF" >&2; exit 1 ;;
esac
for command_name in kubectl php sha256sum cosign; do
  command -v "$command_name" >/dev/null 2>&1 || { echo "Required command is not available: $command_name" >&2; exit 1; }
done

cosign verify --certificate-identity "$COSIGN_IDENTITY" --certificate-oidc-issuer "$COSIGN_ISSUER" "$IMAGE_REF" >/dev/null
cosign verify-attestation --type slsaprovenance --certificate-identity "$COSIGN_IDENTITY" --certificate-oidc-issuer "$COSIGN_ISSUER" "$IMAGE_REF" >/dev/null
cosign verify-attestation --type spdxjson --certificate-identity "$COSIGN_IDENTITY" --certificate-oidc-issuer "$COSIGN_ISSUER" "$IMAGE_REF" >/dev/null

# Deployment is blocked unless admission verification is installed and active.
kubectl get crd clusterpolicies.kyverno.io >/dev/null 2>&1 || {
  echo "Kyverno ClusterPolicy CRD is required for image admission enforcement." >&2
  exit 1
}
sed -e "s|__NAMESPACE__|$NAMESPACE|g" deploy/k8s/network-policies.yaml | kubectl apply -f -
sed \
  -e "s|__NAMESPACE__|$NAMESPACE|g" \
  -e "s|__IMAGE_REPOSITORY__|$IMAGE_REPOSITORY|g" \
  -e "s|__COSIGN_IDENTITY__|$COSIGN_IDENTITY|g" \
  -e "s|__COSIGN_ISSUER__|$COSIGN_ISSUER|g" \
  deploy/k8s/kyverno-image-policy.template.yaml | kubectl apply -f -
kubectl wait --for=condition=Ready clusterpolicy/pesantenku-verify-signed-images --timeout=120s 2>/dev/null ||   kubectl get clusterpolicy/pesantenku-verify-signed-images >/dev/null

case "$OBSERVATION_SECONDS:$MIN_REQUESTS:$MIGRATION_TIMEOUT_SECONDS" in
  *[!0-9:]*|*::*|:*|*:) echo "Canary numeric settings are invalid." >&2; exit 1 ;;
esac
[ "$MIGRATION_TIMEOUT_SECONDS" -gt 0 ] || { echo "MIGRATION_TIMEOUT_SECONDS must be positive." >&2; exit 1; }
php -r 'exit(is_numeric($argv[1]) && (float)$argv[1] >= 0 && (float)$argv[1] <= 1 ? 0 : 1);' "$MAX_ERROR_RATIO" || {
  echo "CANARY_MAX_ERROR_RATIO must be between 0 and 1." >&2; exit 1;
}


verify_live_rollout() {
  expected_image="$1"
  expected_revision="$2"
  kubectl -n "$NAMESPACE" get deployment/"$DEPLOYMENT" -o json > "$LIVE_DEPLOYMENT_FILE"
  selector=$(php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $labels=$d["spec"]["selector"]["matchLabels"]??[]; ksort($labels); echo implode(",",array_map(fn($k)=>$k."=".$labels[$k],array_keys($labels)));' "$LIVE_DEPLOYMENT_FILE")
  [ -n "$selector" ] || { echo "Deployment selector is empty." >&2; return 1; }
  kubectl -n "$NAMESPACE" get pods -l "$selector" -o json > "$LIVE_PODS_FILE"
  php -r '
    $deployment=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
    $pods=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
    $expectedImage=$argv[3]; $expectedRevision=$argv[4];
    $target=array_flip(["app","default-worker","backup-worker","import-worker","export-worker"]);
    $revision=$deployment["spec"]["template"]["metadata"]["labels"]["pesantenku.io/revision"]??null;
    if($revision!==$expectedRevision){fwrite(STDERR,"Unexpected deployment revision\n");exit(2);}
    foreach($deployment["spec"]["template"]["spec"]["containers"]??[] as $container){
      if(isset($target[$container["name"]]) && ($container["image"]??null)!==$expectedImage){fwrite(STDERR,"Unexpected template image for ".$container["name"]."\n");exit(3);}
    }
    $desired=max(1,(int)($deployment["spec"]["replicas"]??1)); $readyPods=0; $digest=substr($expectedImage,strpos($expectedImage,"@sha256:")+1);
    foreach($pods["items"]??[] as $pod){
      if(isset($pod["metadata"]["deletionTimestamp"]))continue;
      if(($pod["metadata"]["labels"]["pesantenku.io/revision"]??null)!==$expectedRevision)continue;
      $spec=[]; foreach($pod["spec"]["containers"]??[] as $c){$spec[$c["name"]]=$c["image"]??"";}
      $status=[]; foreach($pod["status"]["containerStatuses"]??[] as $c){$status[$c["name"]]=$c;}
      $ok=($pod["status"]["phase"]??null)==="Running";
      foreach($target as $name=>$_){
        if(($spec[$name]??null)!==$expectedImage || !($status[$name]["ready"]??false) || !str_contains((string)($status[$name]["imageID"]??""),$digest)){$ok=false;break;}
      }
      if($ok)$readyPods++;
    }
    if($readyPods<$desired){fwrite(STDERR,"Only {$readyPods}/{$desired} pods run the expected immutable image\n");exit(4);}
  ' "$LIVE_DEPLOYMENT_FILE" "$LIVE_PODS_FILE" "$expected_image" "$expected_revision"
}

verify_live_against_snapshot() {
  expected_file="$1"
  kubectl -n "$NAMESPACE" get deployment/"$DEPLOYMENT" -o json > "$LIVE_DEPLOYMENT_FILE"
  selector=$(php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $labels=$d["spec"]["selector"]["matchLabels"]??[]; ksort($labels); echo implode(",",array_map(fn($k)=>$k."=".$labels[$k],array_keys($labels)));' "$LIVE_DEPLOYMENT_FILE")
  [ -n "$selector" ] || { echo "Deployment selector is empty after rollback." >&2; return 1; }
  kubectl -n "$NAMESPACE" get pods -l "$selector" -o json > "$LIVE_PODS_FILE"
  php -r '
    $expected=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
    $live=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
    $pods=json_decode(file_get_contents($argv[3]),true,512,JSON_THROW_ON_ERROR);
    $target=array_flip(["app","default-worker","backup-worker","import-worker","export-worker"]);
    $images=function($document)use($target){$out=[];foreach($document["spec"]["template"]["spec"]["containers"]??[] as $c){if(isset($target[$c["name"]]))$out[$c["name"]]=$c["image"]??"";}ksort($out);return $out;};
    $expectedImages=$images($expected); $liveImages=$images($live);
    $expectedRevision=$expected["spec"]["template"]["metadata"]["labels"]["pesantenku.io/revision"]??null;
    $liveRevision=$live["spec"]["template"]["metadata"]["labels"]["pesantenku.io/revision"]??null;
    if($expectedImages!==$liveImages || $expectedRevision!==$liveRevision){fwrite(STDERR,"Rollback template mismatch\n");exit(2);}
    $desired=max(1,(int)($live["spec"]["replicas"]??1)); $readyPods=0;
    foreach($pods["items"]??[] as $pod){
      if(isset($pod["metadata"]["deletionTimestamp"]) || ($pod["metadata"]["labels"]["pesantenku.io/revision"]??null)!==$expectedRevision)continue;
      $spec=[]; foreach($pod["spec"]["containers"]??[] as $c){$spec[$c["name"]]=$c["image"]??"";}
      $status=[]; foreach($pod["status"]["containerStatuses"]??[] as $c){$status[$c["name"]]=$c;}
      $ok=($pod["status"]["phase"]??null)==="Running";
      foreach($expectedImages as $name=>$image){$digest=str_contains($image,"@")?substr($image,strpos($image,"@")+1):"";if(($spec[$name]??null)!==$image||!($status[$name]["ready"]??false)||($digest!==""&&!str_contains((string)($status[$name]["imageID"]??""),$digest))){$ok=false;break;}}
      if($ok)$readyPods++;
    }
    if($readyPods<$desired){fwrite(STDERR,"Rollback pods do not run the exact previous immutable images\n");exit(3);}
  ' "$expected_file" "$LIVE_DEPLOYMENT_FILE" "$LIVE_PODS_FILE"
}

canary_health() {
  kubectl -n "$NAMESPACE" exec deployment/"$DEPLOYMENT" -c app -- \
    php -r '$c=stream_context_create(["http"=>["timeout"=>10,"header"=>"Host: ".$argv[1]."\r\nAccept: application/json\r\n"]]); $b=@file_get_contents("http://127.0.0.1:8000/health",false,$c); if($b===false){exit(1);} $d=json_decode($b,true); exit(is_array($d)&&($d["status"]??null)==="ok"?0:1);' \
    "$CANARY_HEALTH_HOST"
}

prometheus_value() {
  query="$1"
  kubectl -n "$NAMESPACE" exec deployment/"$DEPLOYMENT" -c app -- \
    php -r '$base=rtrim($argv[1],"/"); $q=$argv[2]; $t=$argv[3]; $c=stream_context_create(["http"=>["timeout"=>15,"ignore_errors"=>true,"header"=>"Authorization: Bearer ".$t."\r\nAccept: application/json\r\n"]]); $b=@file_get_contents($base."/api/v1/query?".http_build_query(["query"=>$q]),false,$c); if($b===false){exit(2);} $d=json_decode($b,true); if(($d["status"]??null)!=="success"){exit(3);} echo $d["data"]["result"][0]["value"][1]??"0";' \
    "$PROMETHEUS" "$query" "$PROMETHEUS_TOKEN"
}

rollback() {
  [ -s "$PREVIOUS_DEPLOYMENT_FILE" ] || { echo "Previous deployment template unavailable." >&2; return 1; }
  php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); file_put_contents($argv[2],json_encode([["op"=>"replace","path"=>"/spec/template","value"=>$d["spec"]["template"]]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));' \
    "$PREVIOUS_DEPLOYMENT_FILE" "$ROLLBACK_PATCH_FILE"
  kubectl -n "$NAMESPACE" patch deployment/"$DEPLOYMENT" --type=json --patch-file "$ROLLBACK_PATCH_FILE"
  kubectl -n "$NAMESPACE" rollout status deployment/"$DEPLOYMENT" --timeout=300s
  verify_live_against_snapshot "$PREVIOUS_DEPLOYMENT_FILE"
}

cleanup() {
  status=$?
  trap - EXIT
  if [ "$status" -ne 0 ] && [ "$MIGRATION_ACTIVE" = true ]; then
    kubectl -n "$NAMESPACE" delete job "$MIGRATION_JOB" --cascade=foreground --wait=true --timeout=120s --ignore-not-found=true || true
  fi
  if [ "$status" -ne 0 ] && [ "$DEPLOYMENT_UPDATED" = true ]; then
    echo "Canary failed; restoring the exact previous PodTemplate." >&2
    rollback || true
  fi
  rm -rf "$STATE_DIR"
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP

kubectl -n "$NAMESPACE" delete job "$MIGRATION_JOB" --ignore-not-found=true >/dev/null
MIGRATION_ACTIVE=true
cat <<EOF | kubectl apply -f -
apiVersion: batch/v1
kind: Job
metadata:
  name: ${MIGRATION_JOB}
  namespace: ${NAMESPACE}
  labels: {app: pesantenku, task: migration}
spec:
  backoffLimit: 0
  activeDeadlineSeconds: ${MIGRATION_TIMEOUT_SECONDS}
  ttlSecondsAfterFinished: 3600
  template:
    metadata:
      labels: {app: pesantenku, task: migration}
    spec:
      automountServiceAccountToken: false
      restartPolicy: Never
      securityContext:
        runAsNonRoot: true
        runAsUser: 82
        runAsGroup: 82
        seccompProfile: {type: RuntimeDefault}
      containers:
        - name: migration
          image: ${IMAGE_REF}
          securityContext:
            allowPrivilegeEscalation: false
            readOnlyRootFilesystem: true
            capabilities: {drop: [ALL]}
          resources:
            requests: {cpu: 100m, memory: 256Mi}
            limits: {cpu: '1', memory: 1Gi}
          volumeMounts:
            - {name: tmp, mountPath: /tmp}
            - {name: storage, mountPath: /app/storage}
            - {name: bootstrap-cache, mountPath: /app/bootstrap/cache}
          command: [sh, -lc]
          args:
            - >-
              php artisan pesantenku:production-check --skip-database --skip-runtime-probes &&
              php artisan pesantenku:domain-preflight &&
              php artisan migrate --force --isolated &&
              php artisan pesantenku:production-check --skip-runtime-probes &&
              php artisan pesantenku:domain-preflight
          envFrom:
            - secretRef: {name: ${MIGRATION_SECRET_NAME}}
      volumes:
        - {name: tmp, emptyDir: {sizeLimit: 128Mi}}
        - {name: storage, emptyDir: {sizeLimit: 1Gi}}
        - {name: bootstrap-cache, emptyDir: {sizeLimit: 128Mi}}
EOF

if ! kubectl -n "$NAMESPACE" wait --for=condition=complete job/"$MIGRATION_JOB" --timeout="${MIGRATION_TIMEOUT_SECONDS}s"; then
  kubectl -n "$NAMESPACE" logs job/"$MIGRATION_JOB" --all-containers=true || true
  kubectl -n "$NAMESPACE" delete job "$MIGRATION_JOB" --cascade=foreground --wait=true --timeout=120s --ignore-not-found=true || true
  MIGRATION_ACTIVE=false
  exit 1
fi
MIGRATION_ACTIVE=false

kubectl -n "$NAMESPACE" get deployment/"$DEPLOYMENT" -o json > "$PREVIOUS_DEPLOYMENT_FILE"
php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $wanted=array_flip(["app","default-worker","backup-worker","import-worker","export-worker"]); $p=[["op"=>"add","path"=>"/spec/template/metadata/labels/pesantenku.io~1revision","value"=>$argv[3]]]; foreach($d["spec"]["template"]["spec"]["containers"] as $i=>$c){if(isset($wanted[$c["name"]])){$p[]=["op"=>"replace","path"=>"/spec/template/spec/containers/{$i}/image","value"=>$argv[2]];unset($wanted[$c["name"]]);}} if($wanted){fwrite(STDERR,"Missing containers: ".implode(",",array_keys($wanted))."\n");exit(2);} file_put_contents($argv[4],json_encode($p,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));' \
  "$PREVIOUS_DEPLOYMENT_FILE" "$IMAGE_REF" "$DIGEST_SUFFIX" "$ROLLOUT_PATCH_FILE"
DEPLOYMENT_UPDATED=true
kubectl -n "$NAMESPACE" patch deployment/"$DEPLOYMENT" --type=json --patch-file "$ROLLOUT_PATCH_FILE"
kubectl -n "$NAMESPACE" rollout status deployment/"$DEPLOYMENT" --timeout=300s
verify_live_rollout "$IMAGE_REF" "$DIGEST_SUFFIX"

attempt=0
until canary_health >/dev/null; do
  attempt=$((attempt + 1)); [ "$attempt" -lt 12 ] || exit 1; sleep 5
done

kubectl -n "$NAMESPACE" exec deployment/"$DEPLOYMENT" -c app -- \
  env CANARY_HEALTH_HOST="$CANARY_HEALTH_HOST" php artisan pesantenku:canary-smoke
kubectl -n "$NAMESPACE" exec deployment/"$DEPLOYMENT" -c app -- \
  php artisan pesantenku:operational-drill

attempt=0
while [ "$attempt" -lt "$MIN_REQUESTS" ]; do
  canary_health >/dev/null
  attempt=$((attempt + 1))
done

sleep "$OBSERVATION_SECONDS"
ERROR_QUERY="sum(rate(pesantenku_http_requests_total{${PROM_SELECTOR},status=~\"5..\"}[5m]))/clamp_min(sum(rate(pesantenku_http_requests_total{${PROM_SELECTOR}}[5m])),0.001)"
COUNT_QUERY="sum(increase(pesantenku_http_requests_total{${PROM_SELECTOR}}[5m]))"
ERROR_RATIO=$(prometheus_value "$ERROR_QUERY")
REQUEST_COUNT=$(prometheus_value "$COUNT_QUERY")
php -r 'exit(((float)$argv[1]) >= (float)$argv[2] ? 0 : 1);' "$REQUEST_COUNT" "$MIN_REQUESTS" || {
  echo "Canary produced only $REQUEST_COUNT observed requests; required $MIN_REQUESTS." >&2; exit 1;
}
php -r 'exit(((float)$argv[1]) <= (float)$argv[2] ? 0 : 1);' "$ERROR_RATIO" "$MAX_ERROR_RATIO" || {
  echo "Canary error ratio $ERROR_RATIO exceeds $MAX_ERROR_RATIO." >&2; exit 1;
}

kubectl -n "$NAMESPACE" annotate deployment/"$DEPLOYMENT" \
  pesantenku.io/canary-status=passed \
  pesantenku.io/canary-image="$IMAGE_REF" \
  pesantenku.io/canary-error-ratio="$ERROR_RATIO" --overwrite
DEPLOYMENT_UPDATED=false
