<?php
$dir = 'C:/xampp/htdocs/Digi Pro X 24/admin';
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Find missing > before emojis
    $fixed = preg_replace('/(\?>|")([📊📁🛍️🛠️⭐📦📋💬👥🔒🌐🚪])/u', '$1>$2', $content);
    
    if ($fixed !== $content) {
        file_put_contents($file, $fixed);
        echo "Fixed: " . basename($file) . "\n";
    }
}
