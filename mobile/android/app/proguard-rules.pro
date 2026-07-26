# Flutter plugins ship consumer rules where required. Keep Flutter entry points and
# native bridge members that are resolved reflectively by the embedding.
-keep class io.flutter.embedding.** { *; }
-keep class io.flutter.plugins.** { *; }
-dontwarn io.flutter.embedding.**
