$file = 'c:\xampp\htdocs\wordpress\wp-content\plugins\myob-integration\stars-myob-connector.php'
$content = [System.IO.File]::ReadAllText($file)

# Fix: sanitize_X($_POST[  -->  sanitize_X(wp_unslash($_POST[
# But avoid double-wrapping anything already unslashed
$patterns = @(
    @{ Old = 'sanitize_email($_POST['; New = 'sanitize_email(wp_unslash($_POST[' },
    @{ Old = 'sanitize_text_field($_POST['; New = 'sanitize_text_field(wp_unslash($_POST[' },
    @{ Old = 'sanitize_textarea_field($_POST['; New = 'sanitize_textarea_field(wp_unslash($_POST[' }
)

foreach ($p in $patterns) {
    if ($content.Contains($p.Old) -and -not $content.Contains($p.New)) {
        $content = $content.Replace($p.Old, $p.New)
        Write-Host "Fixed: $($p.Old)"
    }
}

[System.IO.File]::WriteAllText($file, $content)
Write-Host "Done."
