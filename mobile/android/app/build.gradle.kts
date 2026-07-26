import java.io.FileInputStream
import java.util.Properties
import org.gradle.api.GradleException

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
if (keystorePropertiesFile.exists()) {
    FileInputStream(keystorePropertiesFile).use(keystoreProperties::load)
}

val releaseBuildRequested = gradle.startParameter.taskNames.any { task ->
    task.contains("release", ignoreCase = true)
}
val requiredSigningProperties = listOf("storeFile", "storePassword", "keyAlias", "keyPassword")
val missingSigningProperties = requiredSigningProperties.filter { key ->
    keystoreProperties.getProperty(key).isNullOrBlank()
}
if (releaseBuildRequested && (!keystorePropertiesFile.exists() || missingSigningProperties.isNotEmpty())) {
    throw GradleException(
        "Release signing is not configured. Copy android/key.properties.example to android/key.properties " +
            "and provide: ${requiredSigningProperties.joinToString(", ")}.",
    )
}

android {
    namespace = "id.pesantenku.pesantenku_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        applicationId = "id.pesantenku.pesantenku_mobile"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            val releaseStoreFile = keystoreProperties.getProperty("storeFile")
            if (!releaseStoreFile.isNullOrBlank()) {
                storeFile = file(releaseStoreFile)
            }
            storePassword = keystoreProperties.getProperty("storePassword")
            keyAlias = keystoreProperties.getProperty("keyAlias")
            keyPassword = keystoreProperties.getProperty("keyPassword")
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("release")
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }
    }
}

flutter {
    source = "../.."
}
