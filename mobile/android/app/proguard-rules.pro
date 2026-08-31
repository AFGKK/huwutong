# Flutter ProGuard rules for release build
-keep class com.huwutong.license.** { *; }
-keep class io.flutter.** { *; }
-keep class com.dexterous.** { *; }

# Firebase
-keep class com.google.firebase.** { *; }
-dontwarn com.google.firebase.**
