#!/usr/bin/env sh
set -eu

IMAGE_REF="${1:?immutable image reference is required}"
NAMESPACE="${KUBE_NAMESPACE:-pesantenku}"
DEPLOYMENT="${CANARY_DEPLOYMENT:-pesantenku-canary}"
HEALTH_URL="${CANARY_HEALTH_URL:?CANARY_HEALTH_URL is required}"
PROMETHEUS="${PROMETHEUS_URL:?PROMETHEUS_URL is required}"

rollback() {
  kubectl -n "$NAMESPACE" rollout undo deployment/"$DEPLOYMENT" || true
  kubectl -n "$NAMESPACE" rollout status deployment/"$DEPLOYMENT" --timeout=180s || true
}
trap 'rollback' INT TERM HUP

kubectl -n "$NAMESPACE" set image deployment/"$DEPLOYMENT" app="$IMAGE_REF"
if ! kubectl -n "$NAMESPACE" rollout status deployment/"$DEPLOYMENT" --timeout=300s; then
  rollback
  exit 1
fi

attempt=0
until curl --fail --silent --show-error "$HEALTH_URL/health" >/dev/null; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 12 ]; then
    rollback
    exit 1
  fi
  sleep 5
done

sleep "${CANARY_OBSERVATION_SECONDS:-120}"
QUERY='sum(rate(pesantenku_http_requests_total{status=~"5.."}[5m]))/clamp_min(sum(rate(pesantenku_http_requests_total[5m])),0.001)'
ERROR_RATIO=$(curl --fail --silent --get --data-urlencode "query=$QUERY" "$PROMETHEUS/api/v1/query" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["result"][0]["value"][1] ?? "0";')
if php -r 'exit(((float)$argv[1]) > 0.01 ? 1 : 0);' "$ERROR_RATIO"; then
  kubectl -n "$NAMESPACE" annotate deployment/"$DEPLOYMENT" pesantenku.io/canary-status=passed --overwrite
else
  rollback
  exit 1
fi
