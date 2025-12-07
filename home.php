
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
</head>
<body>
<!-- Navbar -->
 <div class="navbar">
    <div class="logo">Career Pathway</div>
    <div class="navbar-links">
        <a href="home.php">Home</a>
        <a href="about.php">About</a>
        <a href="pathways.php">Pathways</a>
        <a href="assessment.php">Assessment</a>
        <a href="login.php">Login</a>
    </div>
</div>
<!-- Hero Section -->
<div class="hero">
    <h1>Your Future, Unlocked.</h1>

    <div class="carousel" mask>
        <article>
            <img src="images/agri.jpg" alt="">
            <h2>Agriculture</h2>
            <div>
                <p>Agriculture is the science and practice of cultivating crops and raising animals to produce food, fiber, and other resources. Careers in this field include farming, livestock management, agribusiness, and sustainable food production.</p>
                <a href="#">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/cookery.jpg" alt="">
            <h2>Cookery</h2>
            <div>
                <p>Cookery is the art and practice of preparing, cooking, and presenting food. It involves mastering culinary techniques, ensuring food safety, and creating meals that are both nutritious and appealing. A career in cookery can lead to opportunities as a chef, baker, or food entrepreneur in restaurants, hotels, catering services, and other parts of the hospitality industry.</p>

                <a href="#">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/ict.jpg" alt="">
            <h2>ICT</h2>
            <div>
                <p>ICT focuses on the use of technology to manage, process, and share information. It covers computer systems, networks, software, and digital communication tools that are essential in today’s industries. A career in ICT can lead to roles such as software developer, network administrator, IT support specialist, or web designer.</p>

                <a href="#">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/electrical.jpg" alt="">
            <h2>Electrical</h2>
            <div>
                <p>Electrical focuses on the study and application of electricity, electronics, and power systems. It involves installing, maintaining, and repairing electrical wiring, equipment, and machinery. Careers in this field include electricians, electrical technicians, and engineers who work in construction, manufacturing, and energy industries.</p>

                <a href="#">Explore</a>
            </div>
        </article>
        <article>
            <img src="images/smaw.jpg" alt="">
            <h2>SMAW</h2>
            <div>
                <p>SMAW (Welding) involves joining metals using an electric arc and coated electrodes. It is widely used in construction, manufacturing, and repair industries. Careers in this field include welders, fabricators, and metalworkers who build and maintain metal structures, pipelines, and machinery.</p>

                <a href="#">Explore</a>
            </div>
        </article>
    </div>

    <div class="btn-container">
        <a href="assessment.php"><button class="btn btn-yellow">Start Assessment</button></a>
        <a href="pathways.php"><button class="btn btn-black">Explore Pathways</button></a>
    </div>
</div>
</body>
</html>
