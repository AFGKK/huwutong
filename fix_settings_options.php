<?php
// Fix double-encoded options in site_settings
$settings = \App\Models\SiteSetting::whereIn('key', ['page_font_size', 'page_width'])->get();
foreach ($settings as $s) {
    $raw = $s->getRawOriginal('options');
    // Check if it's a string that looks like JSON
    if (is_string($raw) && str_starts_with($raw, '{')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            // Set again - the array cast will properly encode it
            $s->options = $decoded;
            $s->save();
            echo "Fixed: {$s->key}\n";
        }
    }
}
echo "Done.\n";