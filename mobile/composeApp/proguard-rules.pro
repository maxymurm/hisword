# Default ProGuard rules
-keepattributes *Annotation*
-keepattributes SourceFile,LineNumberTable

# Ktor
-keep class io.ktor.** { *; }
-dontwarn io.ktor.**

# Kotlinx Serialization
-keepattributes *Annotation*, InnerClasses
-dontnote kotlinx.serialization.AnnotationsKt
-keepclassmembers class kotlinx.serialization.json.** { *** Companion; }
-keepclasseswithmembers class kotlinx.serialization.json.** { kotlinx.serialization.KSerializer serializer(...); }
-keep,includedescriptorclasses class org.androidbible.**$$serializer { *; }
-keepclassmembers class org.androidbible.** { *** Companion; }
-keepclasseswithmembers class org.androidbible.** { kotlinx.serialization.KSerializer serializer(...); }

# SQLDelight
-keep class app.cash.sqldelight.** { *; }
