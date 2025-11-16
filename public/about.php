<?php
/**
 * ============================================
 * NACOS DASHBOARD - ABOUT US / TEAM PAGE
 * ============================================
 * Purpose: Display information about NACOS, its mission, and its team
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Initialize database
$db = getDB();

// Define a static list of executives for display purposes (ordered for display priority)
$executives = [
    ['full_name' => 'John Doe', 'role' => 'President'],
    ['full_name' => 'Jane Smith', 'role' => 'Vice President'],
    ['full_name' => 'Samuel Green', 'role' => 'General Secretary'],
    ['full_name' => 'Michael Black', 'role' => 'Social Director'],
    ['full_name' => 'Emily White', 'role' => 'Financial Secretary'],
    ['full_name' => 'Chris Brown', 'role' => 'Treasurer'],
    ['full_name' => 'Jessica Blue', 'role' => 'Director of Sports'],
    ['full_name' => 'David Yellow', 'role' => 'Director of Software'],
    ['full_name' => 'Sarah Purple', 'role' => 'Director of Academics'],
    ['full_name' => 'Daniel Orange', 'role' => 'Public Relations Officer'],
    ['full_name' => 'Laura Pink', 'role' => 'Assistant General Secretary'],
];

// Fetch a larger set of active members for the gallery
$active_members = $db->fetchAll(
    "SELECT full_name, matric_no, level FROM MEMBERS WHERE membership_status = 'active' ORDER BY RAND() LIMIT 12"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .page-header {
            background: var(--gradient);
            color: #fff;
            padding: 80px 0;
            text-align: center;
        }
            /* Ensure content sits below the fixed navbar */
            body { padding-top: 80px; }
        .page-header h1 {
            color: #fff;
            font-size: 3rem;
        }
        .mission-section {
            padding: 80px 0;
            background: var(--light-gray);
        }
        .team-section {
            padding: 80px 0;
            overflow: hidden;
        }
        .executives-carousel-wrapper {
            position: relative;
            padding: 0 50px;
        }
        .executives-carousel {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            gap: 20px;
            padding: 20px 0;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        .executives-carousel::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        .executive-card {
            text-align: center;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            min-width: calc(25% - 15px);
            flex-shrink: 0;
        }
        @media (max-width: 992px) {
            .executive-card {
                min-width: calc(33.333% - 15px);
            }
        }
        @media (max-width: 768px) {
            .executive-card {
                min-width: calc(50% - 15px);
            }
        }
        @media (max-width: 576px) {
            .executive-card {
                min-width: calc(100% - 15px);
            }
        }
        .executive-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .executive-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 4px solid var(--light-gray);
        }
        .executive-card h5 {
            font-weight: 600;
        }
        .executive-card p {
            color: var(--primary-color);
            font-weight: 500;
            text-transform: capitalize;
        }
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }
        .carousel-nav:hover {
            background: var(--dark-gray);
            transform: translateY(-50%) scale(1.1);
        }
        .carousel-nav.prev {
            left: 0;
        }
        .carousel-nav.next {
            right: 0;
        }
        .carousel-indicators {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .carousel-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ddd;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .carousel-indicator.active {
            background: var(--primary-color);
            width: 30px;
            border-radius: 5px;
        }
        .member-gallery {
            padding: 80px 0;
            background: var(--gradient);
        }
        .member-gallery .section-header h2,
        .member-gallery .section-header p {
            color: #fff;
        }
        .member-gallery .section-header h2::after {
            background: #fff;
        }
        .member-item {
            text-align: center;
            color: #fff;
        }
        .member-item img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            filter: grayscale(50%);
            transition: all 0.3s ease;
        }
        .member-item:hover img {
            filter: grayscale(0%);
            transform: scale(1.1);
        }
        .member-item h6 {
            color: #fff;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Header (shared) -->
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>

    <!-- Page Header / Hero -->
    <section class="page-header" aria-labelledby="about-heading">
        <div class="container">
            <h1 id="about-heading">About NACOS</h1>
            <p class="lead">We foster a community of learners, builders, and future tech leaders — through events, projects and collaboration.</p>
        </div>
    </section>

    <!-- Mission / What we do -->
    <section class="mission-section" aria-labelledby="mission-heading">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 id="mission-heading">Our Mission</h2>
                    <p class="lead">NACOS exists to connect computer science students through practical learning, mentorship and community projects. We run workshops, hackathons, seminars and hands-on project teams that help members build real-world skills.</p>
                    <ul>
                        <li><strong>Learn:</strong> Workshops and seminars led by professionals and senior students.</li>
                        <li><strong>Build:</strong> Team projects that solve real problems and ship working prototypes.</li>
                        <li><strong>Lead:</strong> Leadership and volunteering opportunities across events and projects.</li>
                    </ul>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="../assets/images/about-illustration.svg" alt="Students collaborating on a project" class="img-fluid" style="max-height:320px;" loading="lazy" decoding="async">
                </div>
            </div>
        </div>
    </section>

    <!-- Executive Team Section -->
    <section class="team-section">
        <div class="container">
            <div class="section-header">
                <h2>Meet the Executives</h2>
                <p>The dedicated team leading NACOS forward.</p>
            </div>
            <?php if (empty($executives)): ?>
                <p class="text-center">Executive team information is currently unavailable.</p>
            <?php else: ?>
                <div class="executives-carousel-wrapper">
                    <button class="carousel-nav prev" onclick="scrollCarousel('left')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="executives-carousel" id="executivesCarousel">
                        <?php foreach ($executives as $executive): ?>
                            <div class="executive-card">
                                <img src="https://i.pravatar.cc/150?u=<?php echo md5($executive['full_name']); ?>" alt="<?php echo htmlspecialchars($executive['full_name']); ?>" loading="lazy" decoding="async">
                                <h5><?php echo htmlspecialchars($executive['full_name']); ?></h5>
                                <p><?php echo htmlspecialchars($executive['role']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-nav next" onclick="scrollCarousel('right')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="carousel-indicators" id="carouselIndicators"></div>
            <?php endif; ?>
        </div>
    </section>
    

    
    <!-- Member Gallery Section -->
    <section class="member-gallery">
        <div class="container">
            <div class="section-header">
                <h2>Our Vibrant Community</h2>
                <p>A glimpse of the brilliant minds that make up our association.</p>
            </div>
            <div class="row">
                <?php foreach ($active_members as $member): ?>
                    <div class="col-lg-2 col-md-3 col-4 mb-4">
                        <div class="member-item" title="<?php echo htmlspecialchars($member['full_name']); ?>">
                            <img src="https://i.pravatar.cc/100?u=<?php echo md5($member['matric_no']); ?>" alt="<?php echo htmlspecialchars($member['full_name']); ?>" loading="lazy" decoding="async">
                            <h6><?php echo htmlspecialchars(explode(' ', $member['full_name'])[0]); ?></h6>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- Footer (shared) -->
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar.fixed-top');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Executives Carousel Functionality
        const carousel = document.getElementById('executivesCarousel');
        const indicatorsContainer = document.getElementById('carouselIndicators');
        
        if (carousel && indicatorsContainer) {
            const cards = carousel.querySelectorAll('.executive-card');
            const totalCards = cards.length;
            const cardsPerView = 4;
            const totalPages = Math.ceil(totalCards / cardsPerView);
            let currentPage = 0;
            
            // Create indicators
            for (let i = 0; i < totalPages; i++) {
                const indicator = document.createElement('button');
                indicator.className = 'carousel-indicator';
                if (i === 0) indicator.classList.add('active');
                indicator.onclick = () => scrollToPage(i);
                indicatorsContainer.appendChild(indicator);
            }
            
            // Scroll to specific page with infinite loop
            function scrollToPage(pageIndex, instant = false) {
                const cardWidth = cards[0].offsetWidth + 20;
                const scrollAmount = cardWidth * cardsPerView * pageIndex;
                
                if (instant) {
                    carousel.scrollTo({ left: scrollAmount, behavior: 'auto' });
                } else {
                    carousel.scrollTo({ left: scrollAmount, behavior: 'smooth' });
                }
                
                currentPage = pageIndex;
                updateIndicators(pageIndex);
            }
            
            // Update active indicator
            function updateIndicators(activeIndex) {
                const indicators = indicatorsContainer.querySelectorAll('.carousel-indicator');
                indicators.forEach((indicator, index) => {
                    if (index === activeIndex) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
            }
            
            // Update indicators on scroll
            carousel.addEventListener('scroll', () => {
                const cardWidth = cards[0].offsetWidth + 20;
                const scrollPosition = carousel.scrollLeft;
                const calculatedPage = Math.round(scrollPosition / (cardWidth * cardsPerView));
                
                if (calculatedPage !== currentPage && calculatedPage >= 0 && calculatedPage < totalPages) {
                    currentPage = calculatedPage;
                    updateIndicators(currentPage);
                }
            });
            
            // Touch/swipe support for mobile
            let startX = 0;
            let scrollLeft = 0;
            
            carousel.addEventListener('touchstart', (e) => {
                startX = e.touches[0].pageX - carousel.offsetLeft;
                scrollLeft = carousel.scrollLeft;
            });
            
            carousel.addEventListener('touchmove', (e) => {
                e.preventDefault();
                const x = e.touches[0].pageX - carousel.offsetLeft;
                const walk = (x - startX) * 2;
                carousel.scrollLeft = scrollLeft - walk;
            });
            
            // Make scrollCarousel function with infinite loop
            window.scrollCarousel = function(direction) {
                const cardWidth = cards[0].offsetWidth + 20;
                const maxScroll = carousel.scrollWidth - carousel.clientWidth;
                
                if (direction === 'left') {
                    // If at the beginning, jump to the end
                    if (carousel.scrollLeft <= 0) {
                        currentPage = totalPages - 1;
                        scrollToPage(currentPage, false);
                    } else {
                        currentPage = Math.max(0, currentPage - 1);
                        scrollToPage(currentPage, false);
                    }
                } else {
                    // If at or near the end, jump to the beginning
                    if (carousel.scrollLeft >= maxScroll - 10) {
                        currentPage = 0;
                        scrollToPage(currentPage, false);
                    } else {
                        currentPage = Math.min(totalPages - 1, currentPage + 1);
                        scrollToPage(currentPage, false);
                    }
                }
            };
        }
    </script>
</body>
</html>
