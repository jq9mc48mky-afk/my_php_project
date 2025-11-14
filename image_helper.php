<?php
/**
 * Processes, resizes, and saves an uploaded image and its thumbnail.
 *
 * @param array $file_data The $_FILES['image'] array.
 * @return string|array The base filename (e.g., 'asset_abc.jpg') on success,
 * or an array of error strings on failure.
 */
function process_and_save_image($file_data) {
    // --- 1. Validation (Moved from computers.php) ---
    $errors = [];
    if (!isset($file_data) || $file_data['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No file uploaded or an error occurred.';
        return $errors;
    }
    if ($file_data['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB.';
    }
    $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file_data['tmp_name']);
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];

    if (!in_array($file_extension, ALLOWED_EXTENSIONS) || !in_array($mime_type, $allowed_mime_types)) {
        $errors[] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
    }
    if (!empty($errors)) {
        return $errors;
    }

    // --- 2. Load Image ---
    $source_image = null;
    switch ($mime_type) {
        case 'image/jpeg': $source_image = imagecreatefromjpeg($file_data['tmp_name']); break;
        case 'image/png': $source_image = imagecreatefrompng($file_data['tmp_name']); break;
        case 'image/gif': $source_image = imagecreatefromgif($file_data['tmp_name']); break;
    }
    if (!$source_image) {
        $errors[] = 'Failed to read image data.';
        return $errors;
    }
    
    // Handle PNG transparency
    if ($mime_type == 'image/png') {
        imagealphablending($source_image, false);
        imagesavealpha($source_image, true);
    }

    $source_width = imagesx($source_image);
    $source_height = imagesy($source_image);

    // --- 3. Generate Filename ---
    $base_filename = uniqid('asset_', true) . '.' . $file_extension;
    $main_path = UPLOAD_DIR . $base_filename;
    $thumb_path = UPLOAD_DIR . preg_replace('/(\.[^.]+)$/', '_thumb$1', $base_filename);

    // --- 4. Process Main Image (Max 1024x1024, preserve ratio) ---
    $max_size = 1024;
    $ratio = $source_width / $source_height;
    if ($source_width > $max_size || $source_height > $max_size) {
        if ($source_width > $source_height) {
            $main_width = $max_size;
            $main_height = (int)($max_size / $ratio);
        } else {
            $main_height = $max_size;
            $main_width = (int)($max_size * $ratio);
        }
    } else {
        $main_width = $source_width;
        $main_height = $source_height;
    }
    $main_image = imagecreatetruecolor($main_width, $main_height);
    if ($mime_type == 'image/png') { // Handle transparency for resized PNG
        imagealphablending($main_image, false);
        imagesavealpha($main_image, true);
        $transparent = imagecolorallocatealpha($main_image, 255, 255, 255, 127);
        imagefilledrectangle($main_image, 0, 0, $main_width, $main_height, $transparent);
    }
    imagecopyresampled($main_image, $source_image, 0, 0, 0, 0, $main_width, $main_height, $source_width, $source_height);

    // --- 5. Process Thumbnail (200x200 Square Crop) ---
    $thumb_size = 200;
    $thumb_image = imagecreatetruecolor($thumb_size, $thumb_size);
    // Handle transparency for thumb
    if ($mime_type == 'image/png') {
        imagealphablending($thumb_image, false);
        imagesavealpha($thumb_image, true);
        $transparent = imagecolorallocatealpha($thumb_image, 255, 255, 255, 127);
        imagefilledrectangle($thumb_image, 0, 0, $thumb_size, $thumb_size, $transparent);
    }

    $src_x = 0; $src_y = 0;
    if ($source_width > $source_height) { // Landscape
        $src_x = (int)(($source_width - $source_height) / 2);
        $source_width_crop = $source_height;
        $source_height_crop = $source_height;
    } else { // Portrait or Square
        $src_y = (int)(($source_height - $source_width) / 2);
        $source_width_crop = $source_width;
        $source_height_crop = $source_width;
    }
    imagecopyresampled($thumb_image, $source_image, 0, 0, $src_x, $src_y, $thumb_size, $thumb_size, $source_width_crop, $source_height_crop);

    // --- 6. Save Both Images ---
    try {
        switch ($mime_type) {
            case 'image/jpeg':
                imagejpeg($main_image, $main_path, 85); // 85% quality
                imagejpeg($thumb_image, $thumb_path, 80);
                break;
            case 'image/png':
                imagepng($main_image, $main_path, 6); // 0-9 compression
                imagepng($thumb_image, $thumb_path, 6);
                break;
            case 'image/gif':
                imagegif($main_image, $main_path);
                imagegif($thumb_image, $thumb_path);
                break;
        }
    } catch (Exception $e) {
        return ['Failed to save image: ' . $e->getMessage()];
    }

    // --- 7. Clean Up Memory ---
    imagedestroy($source_image);
    imagedestroy($main_image);
    imagedestroy($thumb_image);

    return $base_filename; // Success!
}
?>