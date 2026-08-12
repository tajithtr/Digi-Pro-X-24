<?php
$dir = 'C:/xampp/htdocs/Digi Pro X 24/admin';
$files = glob($dir . '/*.php');

$replacements = [
    '/(<a[^>]*href=\"index\.php\"[^>]*>).*?(Admin Dashboard\s*<\/a>)/s' => '$1📊 $2',
    '/(<a[^>]*href=\"categories\.php\"[^>]*>).*?(Categories\s*<\/a>)/s' => '$1📁 $2',
    '/(<a[^>]*href=\"products\.php\"[^>]*>).*?(Products\s*<\/a>)/s' => '$1🛍️ $2',
    '/(<a[^>]*href=\"services\.php\"[^>]*>).*?(Services\s*<\/a>)/s' => '$1🛠️ $2',
    '/(<a[^>]*href=\"reviews\.php\"[^>]*>).*?(Q \& A Reviews\s*(?:<\?php.*?\?>)?\s*<\/a>)/s' => '$1⭐ $2',
    '/(<a[^>]*href=\"orders\.php\"[^>]*>).*?(Orders\s*<\/a>)/s' => '$1📦 $2',
    '/(<a[^>]*href=\"service_requests\.php\"[^>]*>).*?(Service Requests\s*(?:<\?php.*?\?>)?\s*<\/a>)/s' => '$1📋 $2',
    '/(<a[^>]*href=\"messages\.php\"[^>]*>).*?(Messages\s*(?:<\?php.*?\?>)?\s*<\/a>)/s' => '$1💬 $2',
    '/(<a[^>]*href=\"users\.php(?:\?tab=customer)?\"[^>]*>).*?(Users\s*<\/a>)/s' => '$1👥 $2',
    '/(<a[^>]*href=\"change_password\.php\"[^>]*>).*?(Change Password\s*<\/a>)/s' => '$1🔒 $2',
    '/(<a[^>]*href=\"\.\.\/index\.php\"[^>]*>).*?(View Site\s*<\/a>)/s' => '$1🌐 $2',
    '/(<a[^>]*href=\"\.\.\/logout\.php\"[^>]*>).*?(Logout\s*<\/a>)/s' => '$1🚪 $2',
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $newContent = $content;
    
    // Only process if it has a sidebar
    if (strpos($content, 'sidebar-menu') !== false || strpos($content, 'sidebar-brand') !== false) {
        foreach ($replacements as $pattern => $replacement) {
            $newContent = preg_replace($pattern, $replacement, $newContent);
        }
        
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            echo "Updated: " . basename($file) . "\n";
        }
    }
}
?>
