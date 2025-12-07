<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Pathway</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <script src="https://cdn.tailwindcss.com"></script> <!-- Keep for header styles -->
</head>
<body>

<?php include 'header.php'; ?>

<!-- Hero Section -->
<div class="hero">
    <h1>Your Future, Unlocked.</h1>

    <div class="carousel" mask>
        <article>
            <img src="images/agri.jpg" alt="Agriculture">
            <h2>Agriculture</h2>
            <div>
                <p>Agriculture is the science and practice of cultivating crops and raising animals to produce food, fiber, and other resources. Careers in this field include farming, livestock management, agribusiness, and sustainable food production.</p>
                <a href="pathways.php">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/cookery.jpg" alt="Cookery">
            <h2>Cookery</h2>
            <div>
                <p>Cookery is the art and practice of preparing, cooking, and presenting food. It involves mastering culinary techniques, ensuring food safety, and creating meals that are both nutritious and appealing.</p>
                <a href="pathways.php">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/ict.jpg" alt="ICT">
            <h2>ICT</h2>
            <div>
                <p>ICT focuses on the use of technology to manage, process, and share information. It covers computer systems, networks, software, and digital communication tools that are essential in today’s industries.</p>
                <a href="pathways.php">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/electrical.jpg" alt="Electrical">
            <h2>Electrical</h2>
            <div>
                <p>Electrical focuses on the study and application of electricity, electronics, and power systems. It involves installing, maintaining, and repairing electrical wiring, equipment, and machinery.</p>
                <a href="pathways.php">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/smaw.jpg" alt="SMAW">
            <h2>SMAW</h2>
            <div>
                <p>SMAW (Welding) involves joining metals using an electric arc and coated electrodes. It is widely used in construction, manufacturing, and repair industries for building and maintaining metal structures.</p>
                <a href="pathways.php">Explore</a>
            </div>
        </article>
    </div>


</div>

<?php include 'auth_modal.php'; ?>
</body>
</html>