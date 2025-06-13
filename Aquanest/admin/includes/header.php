<?php
// File: includes/header.php

/**
 * Modern header for Aquanest Admin Panel
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?php echo isset($page_title) ? $page_title . ' - Aquanest Admin' : 'Aquanest Admin Panel'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/aquanest-admin.css" rel="stylesheet">
    <link href="css/aquanest-sidebar.css" rel="stylesheet">
    
    <?php if (isset($page_specific_css)): ?>
    <!-- Page Specific CSS -->
    <link href="<?php echo $page_specific_css; ?>" rel="stylesheet">
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../img/favicon.ico" type="image/x-icon">
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content Container -->
    <div class="main-content">