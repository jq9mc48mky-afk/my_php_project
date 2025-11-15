<?php

/**
 * Image Processing Helper Function
 *
 * This file defines a function for handling image uploads, validation,
 * resizing, and thumbnail generation.
 *
 * @global int MAX_FILE_SIZE Constant defined in computers.php (e.g., 5 * 1024 * 1024)
 * @global array ALLOWED_EXTENSIONS Constant defined in computers.php (e.g., ['jpg', 'png'])
 * @global string UPLOAD_DIR Constant defined in computers.php (e.g., 'uploads/')
 */

/**
 * Processes, resizes, and saves an uploaded image and its thumbnail.
 *
 * This function performs the following steps:
 * 1. Validates the uploaded file against size, extension, and MIME type.
 * 2. Loads the image into memory using GD functions (e.g., imagecreatefromjpeg).
 * 3. Generates a unique filename (e.g., 'asset_65a7f...jpg').
 * 4. Resizes the main image to a maximum of 1024x1024, preserving aspect ratio.
 * 5. Creates a 200x200 square thumbnail by cropping from the center.
 * 6. Saves both the main image and the thumbnail to the UPLOAD_DIR.
 * 7. Cleans up GD image resources from memory.
 *
 * @param array $file_data The $_FILES['image'] array from the form.
 * @return string|array The base filename (e.g., 'asset_abc.jpg') on success,
 * or an array of error strings on failure.
 */
function process_and_save_image($file_data)
{
    // --- 1. Validation ---
    $errors = [];
    if (!isset($file_data) || $file_data['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No file uploaded or an error occurred.';
        return $errors;
    }
    // Check against max size defined in computers.php
    if ($file_data['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB.';
    }

    // Check extension
    $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));

    // Check MIME type (more secure than just extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file_data['tmp_name']);
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];

    if (!in_array($file_extension, ALLOWED_EXTENSIONS) || !in_array($mime_type, $allowed_mime_types)) {
        $errors[] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
    }
    if (!empty($errors)) {
        return $errors; // Return array of errors
    }

    // --- 2. Load Image into Memory ---
    $source_image = null;
    switch ($mime_type) {
        case 'image/jpeg': $source_image = imagecreatefromjpeg($file_data['tmp_name']);
            break;
        case 'image/png': $source_image = imagecreatefrompng($file_data['tmp_name']);
            break;
        case 'image/gif': $source_image = imagecreatefromgif($file_data['tmp_name']);
            break;
    }
    if (!$source_image) {
        $errors[] = 'Failed to read image data.';
        return $errors;
    }

    // Preserve transparency for PNGs
    if ($mime_type == 'image/png') {
        imagealphablending($source_image, false);
        imagesavealpha($source_image, true);
    }

    $source_width = imagesx($source_image);
    $source_height = imagesy($source_image);

    // --- 3. Generate Filename ---
    // Use uniqid() for a secure, unique filename to prevent overwrites and conflicts.
    $base_filename = uniqid('asset_', true) . '.' . $file_extension;
    $main_path = UPLOAD_DIR . $base_filename;
    // e.g., 'uploads/asset_..._thumb.jpg'
    $thumb_path = UPLOAD_DIR . preg_replace('/(\.[^.]+)$/', '_thumb$1', $base_filename);

    // --- 4. Process Main Image (Max 1024x1024, preserve ratio) ---
    $max_size = 1024;
    $ratio = $source_width / $source_height;

    // Calculate new dimensions while preserving aspect ratio
    if ($source_width > $max_size || $source_height > $max_size) {
        if ($source_width > $source_height) { // Landscape
            $main_width = $max_size;
            $main_height = (int)($max_size / $ratio);
        } else { // Portrait or Square
            $main_height = $max_size;
            $main_width = (int)($max_size * $ratio);
        }
    } else {
        // Image is already within limits, no resize needed
        $main_width = $source_width;
        $main_height = $source_height;
    }

    $main_image = imagecreatetruecolor($main_width, $main_height);

    // Handle transparency for the new resized PNG
    if ($mime_type == 'image/png') {
        imagealphablending($main_image, false);
        imagesavealpha($main_image, true);
        $transparent = imagecolorallocatealpha($main_image, 255, 255, 255, 127);
        imagefilledrectangle($main_image, 0, 0, $main_width, $main_height, $transparent);
    }
    // Copy and resize the original image to the new canvas
    imagecopyresampled($main_image, $source_image, 0, 0, 0, 0, $main_width, $main_height, $source_width, $source_height);

    // --- 5. Process Thumbnail (200x200 Square Crop from Center) ---
    $thumb_size = 200;
    $thumb_image = imagecreatetruecolor($thumb_size, $thumb_size);

    // Handle transparency for the new thumb
    if ($mime_type == 'image/png') {
        imagealphablending($thumb_image, false);
        imagesavealpha($thumb_image, true);
        $transparent = imagecolorallocatealpha($thumb_image, 255, 255, 255, 127);
        imagefilledrectangle($thumb_image, 0, 0, $thumb_size, $thumb_size, $transparent);
    }

    // --- Center-Crop Logic ---
    $src_x = 0; // Source X coordinate
    $src_y = 0; // Source Y coordinate
    if ($source_width > $source_height) { // Landscape image
        // Crop from the horizontal center
        $src_x = (int)(($source_width - $source_height) / 2);
        $source_width_crop = $source_height;
        $source_height_crop = $source_height;
    } else { // Portrait or Square image
        // Crop from the vertical center
        $src_y = (int)(($source_height - $source_width) / 2);
        $source_width_crop = $source_width;
        $source_height_crop = $source_width;
    }
    // Copy and resize (and crop) the original image to the thumbnail canvas
    imagecopyresampled($thumb_image, $source_image, 0, 0, $src_x, $src_y, $thumb_size, $thumb_size, $source_width_crop, $source_height_crop);

    // --- 6. Save Both Images to Disk ---
    try {
        switch ($mime_type) {
            case 'image/jpeg':
                imagejpeg($main_image, $main_path, 85); // 85% quality
                imagejpeg($thumb_image, $thumb_path, 80);
                break;
            case 'image/png':
                imagepng($main_image, $main_path, 6); // 0-9 compression level
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
    // Free up resources used by the GD image objects.
    imagedestroy($source_image);
    imagedestroy($main_image);
    imagedestroy($thumb_image);

    return $base_filename; // Success! Return the unique filename.
}
