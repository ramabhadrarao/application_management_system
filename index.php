<?php
/**
 * Homepage - SWARNANDHRA College
 * 
 * File: index.php
 * Purpose: Beautiful homepage with course information and application access
 * Author: Student Application Management System
 * Created: 2025
 */

$public_page = true; // This is a public page

// Check if user is already logged in
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? null;

require_once 'config/config.php';

// Create a simple program class for the homepage to avoid path issues
class SimpleProgram {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAllActivePrograms() {
        $query = "SELECT * FROM programs WHERE is_active = 1 ORDER BY display_order ASC, program_name ASC";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
}

$database = new Database();
$db = $database->getConnection();
$program = new SimpleProgram($db);

// Get active programs
$programs = $program->getAllActivePrograms();

// Group programs by type
$grouped_programs = [
    'UG' => [],
    'PG' => [],
    'Diploma' => [],
    'Certificate' => []
];

foreach ($programs as $prog) {
    if (isset($grouped_programs[$prog['program_type']])) {
        $grouped_programs[$prog['program_type']][] = $prog;
    }
}

$page_title = 'Welcome to SWARNANDHRA College';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?php echo SITE_NAME; ?> - Premier Educational Institution</title>
    <meta name="description" content="SWARNANDHRA College offers quality education in Engineering, Computer Applications, Management and more. Apply online for admission.">
    <meta name="keywords" content="engineering college, computer applications, MBA, MCA, B.Tech, admissions, education">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
    <!-- CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet"/>
    
    <style>
        :root {
            --primary-color: #0054a6;
            --secondary-color: #667eea;
            --accent-color: #764ba2;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
        }
        
        /* Hero Section */
        .hero-section {
            position: relative;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .swiper {
            width: 100%;
            height: 100%;
        }
        
        .swiper-slide {
            background-position: center;
            background-size: cover;
            position: relative;
        }
        
        .swiper-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 84, 166, 0.7);
            z-index: 1;
        }
        
        .slide-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            z-index: 2;
            width: 90%;
            max-width: 800px;
        }
        
        .slide-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .slide-content p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        .hero-stats {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 2rem;
            z-index: 2;
        }
        
        .stat-item {
            text-align: center;
            color: white;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Navigation */
        .navbar-homepage {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand-homepage {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .brand-logo {
            width: 50px;
            height: 50px;
            margin-right: 1rem;
            border-radius: 8px;
        }
        
        /* Sections */
        .section {
            padding: 5rem 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Course Cards */
        .course-category {
            margin-bottom: 3rem;
        }
        
        .category-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }
        
        .category-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .category-subtitle {
            opacity: 0.9;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            background: white;
            padding: 2rem;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .course-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,84,166,0.15);
            border-color: var(--primary-color);
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .course-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .course-code {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .course-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .course-duration {
            display: flex;
            align-items: center;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .course-seats {
            background: var(--bg-light);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        /* Features Section */
        .features-section {
            background: var(--bg-light);
        }
        
        .feature-card {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }
        
        .feature-description {
            color: var(--text-light);
            line-height: 1.6;
        }
        
        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
        }
        
        .cta-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-cta {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-cta-primary {
            background: white;
            color: var(--primary-color);
        }
        
        .btn-cta-primary:hover {
            background: var(--bg-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            color: var(--primary-color);
        }
        
        .btn-cta-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-cta-outline:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        /* Footer */
        .footer {
            background: var(--text-dark);
            color: white;
            padding: 3rem 0 1rem;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h5 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: white;
        }
        
        .footer-section a {
            color: #cbd5e0;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-section a:hover {
            color: white;
        }
        
        .footer-bottom {
            border-top: 1px solid #4a5568;
            padding-top: 1rem;
            text-align: center;
            color: #cbd5e0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .slide-content h1 {
                font-size: 2.5rem;
            }
            
            .slide-content p {
                font-size: 1rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .course-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
        
        /* Loading animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e9ecef;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-homepage">
        <div class="container">
            <a class="navbar-brand-homepage" href="/">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>" class="brand-logo" 
                     onerror="this.style.display='none'">
                <span><?php echo SITE_NAME; ?></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#courses">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if ($is_logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white ms-2" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-primary ms-2" href="auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white ms-2" href="auth/register.php">
                            <i class="fas fa-user-plus me-1"></i>Apply Now
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section with Slider -->
    <section id="home" class="hero-section">
        <div class="swiper heroSwiper">
            <div class="swiper-wrapper">
                <!-- Slide 1 -->
                <div class="swiper-slide" style="background-image: url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80')">
                    <div class="slide-content">
                        <h1>Excellence in Education</h1>
                        <p>Empowering students with cutting-edge technology and world-class faculty to shape tomorrow's leaders.</p>
                        <div>
                            <a href="auth/register.php" class="btn-cta btn-cta-primary">
                                <i class="fas fa-graduation-cap"></i>Apply for Admission
                            </a>
                            <a href="#courses" class="btn-cta btn-cta-outline ms-3">
                                <i class="fas fa-arrow-down"></i>Explore Courses
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Slide 2 -->
                <div class="swiper-slide" style="background-image: url('https://images.unsplash.com/photo-1571260899304-425eee4c7efc?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80')">
                    <div class="slide-content">
                        <h1>Modern Campus</h1>
                        <p>State-of-the-art facilities, laboratories, and infrastructure designed for holistic development.</p>
                        <div>
                            <a href="auth/register.php" class="btn-cta btn-cta-primary">
                                <i class="fas fa-university"></i>Virtual Tour
                            </a>
                            <a href="#about" class="btn-cta btn-cta-outline ms-3">
                                <i class="fas fa-info-circle"></i>Learn More
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Slide 3 -->
                <div class="swiper-slide" style="background-image: url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80')">
                    <div class="slide-content">
                        <h1>Industry Partnerships</h1>
                        <p>Strong industry connections ensuring 100% placement assistance and real-world experience.</p>
                        <div>
                            <a href="auth/register.php" class="btn-cta btn-cta-primary">
                                <i class="fas fa-handshake"></i>Career Opportunities
                            </a>
                            <a href="#contact" class="btn-cta btn-cta-outline ms-3">
                                <i class="fas fa-phone"></i>Contact Us
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="swiper-pagination"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
        
        <!-- Hero Stats -->
        <div class="hero-stats">
            <div class="stat-item">
                <span class="stat-number">15+</span>
                <span class="stat-label">Years of Excellence</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">2500+</span>
                <span class="stat-label">Students</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">25+</span>
                <span class="stat-label">Programs</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">100%</span>
                <span class="stat-label">Placement</span>
            </div>
        </div>
    </section>
    
    <!-- Courses Section -->
    <section id="courses" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Courses Offered</h2>
                <p>Discover our comprehensive range of programs designed to meet industry demands and prepare you for a successful career.</p>
            </div>
            
            <!-- Static Course Information (as provided) -->
            
            <!-- UG Engineering Courses -->
            <div class="course-category">
                <div class="category-header">
                    <h3 class="category-title">
                        <i class="fas fa-cogs me-2"></i>B.Tech Engineering (4 Years)
                    </h3>
                    <p class="category-subtitle">Undergraduate Engineering Programs</p>
                </div>
                <div class="course-grid">
                    <div class="course-card" onclick="applyForCourse('BTECH-CSE')">
                        <h4 class="course-title">Computer Science and Engineering</h4>
                        <div class="course-code">B.Tech CSE</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">600 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-CSE-DS')">
                        <h4 class="course-title">CSE (Data Science)</h4>
                        <div class="course-code">B.Tech CSE-DS</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">120 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-AIML')">
                        <h4 class="course-title">Artificial Intelligence and Machine Learning</h4>
                        <div class="course-code">B.Tech AI-ML</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">180 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-IT')">
                        <h4 class="course-title">Information Technology</h4>
                        <div class="course-code">B.Tech IT</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">120 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-CSE-CS')">
                        <h4 class="course-title">CSE (Cyber Security)</h4>
                        <div class="course-code">B.Tech CSE-CS</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-CSE-BS')">
                        <h4 class="course-title">CSE and Business Systems</h4>
                        <div class="course-code">B.Tech CSE-BS</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-AI-DS')">
                        <h4 class="course-title">Artificial Intelligence (AI) and Data Science</h4>
                        <div class="course-code">B.Tech AI-DS</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-ECE')">
                        <h4 class="course-title">Electronics and Communication Engineering</h4>
                        <div class="course-code">B.Tech ECE</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">300 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-CE')">
                        <h4 class="course-title">Civil Engineering</h4>
                        <div class="course-code">B.Tech CE</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-EEE')">
                        <h4 class="course-title">Electrical and Electronics Engineering</h4>
                        <div class="course-code">B.Tech EEE</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-ME')">
                        <h4 class="course-title">Mechanical Engineering</h4>
                        <div class="course-code">B.Tech ME</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BTECH-ROBOTICS')">
                        <h4 class="course-title">Robotics Engineering</h4>
                        <div class="course-code">B.Tech Robotics</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>4 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- UG Degree Courses -->
            <div class="course-category">
                <div class="category-header">
                    <h3 class="category-title">
                        <i class="fas fa-graduation-cap me-2"></i>UG Degree Courses (3 Years)
                    </h3>
                    <p class="category-subtitle">Undergraduate Degree Programs</p>
                </div>
                <div class="course-grid">
                    <div class="course-card" onclick="applyForCourse('BCA')">
                        <h4 class="course-title">Bachelor of Computer Applications</h4>
                        <div class="course-code">BCA</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>3 Years
                            </span>
                            <span class="course-seats">180 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('BBA')">
                        <h4 class="course-title">Bachelor of Business Administration</h4>
                        <div class="course-code">BBA</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>3 Years
                            </span>
                            <span class="course-seats">180 Seats</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- PG Courses -->
            <div class="course-category">
                <div class="category-header">
                    <h3 class="category-title">
                        <i class="fas fa-user-graduate me-2"></i>PG Courses (2 Years)
                    </h3>
                    <p class="category-subtitle">Postgraduate Programs - M.Tech/MBA/MCA</p>
                </div>
                <div class="course-grid">
                    <div class="course-card" onclick="applyForCourse('MCA')">
                        <h4 class="course-title">Master of Computer Applications</h4>
                        <div class="course-code">MCA</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>2 Years
                            </span>
                            <span class="course-seats">240 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('MBA')">
                        <h4 class="course-title">Master of Business Administration</h4>
                        <div class="course-code">MBA</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>2 Years
                            </span>
                            <span class="course-seats">120 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-grid">
                        <div class="course-card" onclick="applyForCourse('MTECH-SE')">
                            <h4 class="course-title">M.Tech - Structural Engineering</h4>
                            <div class="course-code">M.Tech SE</div>
                            <div class="course-details">
                                <span class="course-duration">
                                    <i class="fas fa-clock me-1"></i>2 Years
                                </span>
                                <span class="course-seats">18 Seats</span>
                            </div>
                        </div>
                        
                        <div class="course-card" onclick="applyForCourse('MTECH-PES')">
                            <h4 class="course-title">M.Tech - Power Electronics and Systems</h4>
                            <div class="course-code">M.Tech PES</div>
                            <div class="course-details">
                                <span class="course-duration">
                                    <i class="fas fa-clock me-1"></i>2 Years
                                </span>
                                <span class="course-seats">18 Seats</span>
                            </div>
                        </div>
                        
                        <div class="course-card" onclick="applyForCourse('MTECH-CAD')">
                            <h4 class="course-title">M.Tech - CAD/CAM</h4>
                            <div class="course-code">M.Tech CAD/CAM</div>
                            <div class="course-details">
                                <span class="course-duration">
                                    <i class="fas fa-clock me-1"></i>2 Years
                                </span>
                                <span class="course-seats">18 Seats</span>
                            </div>
                        </div>
                        
                        <div class="course-card" onclick="applyForCourse('MTECH-VLSI')">
                            <h4 class="course-title">M.Tech - VLSI System Design</h4>
                            <div class="course-code">M.Tech VLSI</div>
                            <div class="course-details">
                                <span class="course-duration">
                                    <i class="fas fa-clock me-1"></i>2 Years
                                </span>
                                <span class="course-seats">18 Seats</span>
                            </div>
                        </div>
                        
                        <div class="course-card" onclick="applyForCourse('MTECH-CSE')">
                            <h4 class="course-title">M.Tech - Computer Science and Engineering</h4>
                            <div class="course-code">M.Tech CSE</div>
                            <div class="course-details">
                                <span class="course-duration">
                                    <i class="fas fa-clock me-1"></i>2 Years
                                </span>
                                <span class="course-seats">36 Seats</span>
                            </div>
                        </div>
                        
                        <div class="course-card" onclick="applyForCourse('MTECH-CSE-AIML')">
                            <h4 class="course-title">M.Tech - CSE (AI and ML)</h4>
                            <div class="course-code">M.Tech CSE-AIML</div>
                            <div class="course-details">
                                <span class="course-duration">
                                    <i class="fas fa-clock me-1"></i>2 Years
                                </span>
                                <span class="course-seats">18 Seats</span>
                            </div>
                        </div>
                        
                        <div class="course-card" onclick="applyForCourse('MTECH-IOT')">
                            <h4 class="course-title">M.Tech - Internet of Things</h4>
                            <div class="course-code">M.Tech IoT</div>
                            <div class="course-details">
                                <span class="course-duration">
                                    <i class="fas fa-clock me-1"></i>2 Years
                                </span>
                                <span class="course-seats">18 Seats</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Diploma Courses -->
            <div class="course-category">
                <div class="category-header">
                    <h3 class="category-title">
                        <i class="fas fa-certificate me-2"></i>Diploma Courses (3 Years)
                    </h3>
                    <p class="category-subtitle">Professional Diploma Programs</p>
                </div>
                <div class="course-grid">
                    <div class="course-card" onclick="applyForCourse('DIPLOMA-EEE')">
                        <h4 class="course-title">Diploma - Electrical and Electronics Engineering</h4>
                        <div class="course-code">Diploma EEE</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>3 Years
                            </span>
                            <span class="course-seats">30 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('DIPLOMA-ME')">
                        <h4 class="course-title">Diploma - Mechanical Engineering</h4>
                        <div class="course-code">Diploma ME</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>3 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('DIPLOMA-ECE')">
                        <h4 class="course-title">Diploma - Electronics and Communication Engineering</h4>
                        <div class="course-code">Diploma ECE</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>3 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('DIPLOMA-CSE')">
                        <h4 class="course-title">Diploma - Computer Science and Engineering</h4>
                        <div class="course-code">Diploma CSE</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>3 Years
                            </span>
                            <span class="course-seats">180 Seats</span>
                        </div>
                    </div>
                    
                    <div class="course-card" onclick="applyForCourse('DIPLOMA-AIML')">
                        <h4 class="course-title">Diploma - Artificial Intelligence (AI) and Machine Learning</h4>
                        <div class="course-code">Diploma AI-ML</div>
                        <div class="course-details">
                            <span class="course-duration">
                                <i class="fas fa-clock me-1"></i>3 Years
                            </span>
                            <span class="course-seats">60 Seats</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section id="about" class="section features-section">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose SWARNANDHRA?</h2>
                <p>Experience excellence in education with our state-of-the-art facilities and industry-focused curriculum.</p>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h4 class="feature-title">Expert Faculty</h4>
                        <p class="feature-description">Learn from industry experts and experienced professors with decades of academic and practical experience.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        <h4 class="feature-title">Modern Labs</h4>
                        <p class="feature-description">State-of-the-art laboratories equipped with latest technology for hands-on learning and research.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h4 class="feature-title">100% Placement</h4>
                        <p class="feature-description">Strong industry partnerships ensuring excellent placement opportunities with top companies.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <h4 class="feature-title">NAAC Accredited</h4>
                        <p class="feature-description">Recognized by UGC and accredited by NAAC with top rankings in academic excellence.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="feature-title">Vibrant Campus Life</h4>
                        <p class="feature-description">Active student communities, cultural events, sports, and extracurricular activities for overall development.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4 class="feature-title">Scholarships</h4>
                        <p class="feature-description">Merit-based scholarships and financial assistance programs to support deserving students.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="section cta-section">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title">Ready to Start Your Journey?</h2>
                <p class="cta-subtitle">Join thousands of successful alumni who started their career journey with us. Apply now for the upcoming academic year.</p>
                <div class="cta-buttons">
                    <a href="auth/register.php" class="btn-cta btn-cta-primary">
                        <i class="fas fa-graduation-cap"></i>Apply for Admission
                    </a>
                    <a href="#contact" class="btn-cta btn-cta-outline">
                        <i class="fas fa-phone"></i>Contact Admissions
                    </a>
                    <?php if (!$is_logged_in): ?>
                    <a href="auth/login.php" class="btn-cta btn-cta-outline">
                        <i class="fas fa-sign-in-alt"></i>Student Login
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h5>Contact Information</h5>
                    <p><i class="fas fa-map-marker-alt me-2"></i>SWARNANDHRA College<br>Academic Excellence Road<br>Hyderabad, Telangana</p>
                    <p><i class="fas fa-phone me-2"></i><?php echo SUPPORT_PHONE ?? '+91-9876543210'; ?></p>
                    <p><i class="fas fa-envelope me-2"></i><?php echo ADMIN_EMAIL ?? 'admin@swarnandhra.edu'; ?></p>
                </div>
                
                <div class="footer-section">
                    <h5>Quick Links</h5>
                    <p><a href="#home">Home</a></p>
                    <p><a href="#courses">Courses</a></p>
                    <p><a href="#about">About Us</a></p>
                    <p><a href="auth/register.php">Admissions</a></p>
                    <p><a href="#contact">Contact</a></p>
                </div>
                
                <div class="footer-section">
                    <h5>Student Services</h5>
                    <p><a href="auth/login.php">Student Portal</a></p>
                    <p><a href="auth/register.php">Online Application</a></p>
                    <p><a href="/help.php">Help & Support</a></p>
                    <p><a href="/fees.php">Fee Structure</a></p>
                    <p><a href="/scholarships.php">Scholarships</a></p>
                </div>
                
                <div class="footer-section">
                    <h5>Follow Us</h5>
                    <p>
                        <a href="#" class="me-3"><i class="fab fa-facebook-f"></i> Facebook</a><br>
                        <a href="#" class="me-3"><i class="fab fa-twitter"></i> Twitter</a><br>
                        <a href="#" class="me-3"><i class="fab fa-instagram"></i> Instagram</a><br>
                        <a href="#" class="me-3"><i class="fab fa-linkedin-in"></i> LinkedIn</a><br>
                        <a href="#" class="me-3"><i class="fab fa-youtube"></i> YouTube</a>
                    </p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. | 
                   <a href="/privacy.php">Privacy Policy</a> | 
                   <a href="/terms.php">Terms & Conditions</a>
                </p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <script>
        // Initialize Swiper
        var swiper = new Swiper('.heroSwiper', {
            slidesPerView: 1,
            spaceBetween: 0,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            }
        });
        
        // Function to apply for course
        function applyForCourse(courseCode) {
            window.location.href = `auth/register.php?course=${encodeURIComponent(courseCode)}`;
        }
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Navbar transparency on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-homepage');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.backdropFilter = 'blur(20px)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            }
        });
        
        // Loading overlay
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.opacity = '0';
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 500);
        });
        
        // Counter animation for hero stats
        function animateCounter(element, start, end, duration) {
            let startTime = null;
            const step = (timestamp) => {
                if (!startTime) startTime = timestamp;
                const progress = Math.min((timestamp - startTime) / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                const text = element.textContent;
                const hasPlus = text.includes('+');
                const hasPercent = text.includes('%');
                element.textContent = current + (hasPlus ? '+' : '') + (hasPercent ? '%' : '');
                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };
            requestAnimationFrame(step);
        }
        
        // Trigger counter animation when stats come into view
        const statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumbers = entry.target.querySelectorAll('.stat-number');
                    statNumbers.forEach((stat, index) => {
                        const text = stat.textContent;
                        const number = parseInt(text.replace(/\D/g, ''));
                        setTimeout(() => {
                            animateCounter(stat, 0, number, 2000);
                        }, index * 200);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        const heroStats = document.querySelector('.hero-stats');
        if (heroStats) {
            statsObserver.observe(heroStats);
        }
    </script>
</body>
</html>