<?php
$file = '/Users/saifullah/Projects/Full Ecommarce/marketing-app/app/Modules/Commerce/Resources/views/user/form.blade.php';
$content = file_get_contents($file);

// Find Step 6 (Wholesale)
$step6_start = strpos($content, '        {{-- STEP 6: Wholesale Settings --}}');
$step6_end = strpos($content, '            </form>', $step6_start) + strlen('            </form>') + 1; // get newline

$step6_block = substr($content, $step6_start, $step6_end - $step6_start);

// Remove it from current position
$content = substr_replace($content, '', $step6_start, $step6_end - $step6_start);

// Find where to insert (right after step 5's </form> and before step 7)
// Step 7 starts with: '        {{-- STEP 7: Feature Highlights (Icons) --}}'
$step7_start = strpos($content, '        {{-- STEP 7: Feature Highlights (Icons) --}}');

// Insert it there
$content = substr_replace($content, $step6_block . "\n", $step7_start, 0);

file_put_contents($file, $content);
echo "Moved successfully.\n";
