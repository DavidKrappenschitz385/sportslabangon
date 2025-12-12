<?php
// index.php - Main Landing Page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sports League Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', sans-serif; 
            line-height: 1.6; 
            color: #333; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .hero-section { 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            text-align: center; 
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .hero-content { 
            max-width: 800px; 
            padding: 2rem; 
            z-index: 2;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
        }
        
        h1 { 
            font-size: 3.5rem; 
            margin-bottom: 1rem; 
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle { 
            font-size: 1.3rem; 
            margin-bottom: 2rem; 
            opacity: 0.9;
        }
        
        .cta-buttons { 
            display: flex; 
            gap: 1rem; 
            justify-content: center; 
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .btn { 
            display: inline-block; 
            padding: 1rem 2rem; 
            text-decoration: none; 
            border-radius: 50px; 
            font-weight: bold; 
            transition: all 0.3s ease; 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-primary { 
            background: #007bff; 
            color: white; 
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-primary:hover { 
            background: #0056b3; 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        
        .btn-secondary { 
            background: rgba(255, 255, 255, 0.2); 
            color: white; 
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary:hover { 
            background: rgba(255, 255, 255, 0.3); 
            transform: translateY(-2px);
        }
        
        .features-section { 
            background: white; 
            padding: 5rem 2rem; 
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        
        .features-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 2rem; 
            margin-top: 3rem;
        }
        
        .feature-card { 
            background: #f8f9fa; 
            padding: 2rem; 
            border-radius: 10px; 
            text-align: center; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon { 
            font-size: 3rem; 
            margin-bottom: 1rem; 
        }
        
        .feature-card h3 { 
            color: #007bff; 
            margin-bottom: 1rem; 
        }
        
        .stats-section { 
            background: #343a40; 
            color: white; 
            padding: 3rem 2rem; 
            text-align: center;
        }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 2rem; 
            margin-top: 2rem;
        }
        
        .stat-item h3 { 
            font-size: 2.5rem; 
            color: #007bff; 
            margin-bottom: 0.5rem;
        }
        
        .footer { 
            background: #212529; 
            color: white; 
            padding: 2rem; 
            text-align: center;
        }
        
        @media (max-width: 768px) {
            h1 { font-size: 2.5rem; }
            .hero-subtitle { font-size: 1.1rem; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .btn { padding: 0.8rem 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1> Web-Based Sports League Registration System</h1>
            <p class="hero-subtitle">
                Complete solution for managing sports leagues, teams, players, and tournaments. 
                Built with PHP, MySQL, and modern web technologies.
            </p>
            
            <div class="cta-buttons">
                <a href="auth/login.php" class="btn btn-primary">Login to System</a>
                <a href="auth/register.php" class="btn btn-secondary">Register Now</a>
            </div>
            
            <p style="margin-top: 2rem; opacity: 0.8;">
                New to the system? <a href="#features" style="color: #ffc107;">Learn more about our features</a>
            </p>
        </div>
    </section>
    
    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 style="text-align: center; font-size: 2.5rem; color: #343a40; margin-bottom: 1rem;">
                Powerful Features
            </h2>
            <p style="text-align: center; font-size: 1.2rem; color: #666; max-width: 600px; margin: 0 auto;">
                Everything you need to run a successful sports league, from registration to championship
            </p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>User Management</h3>
                    <p>Complete user registration system with role-based access control. Support for players, team owners, and administrators.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🏆</div>
                    <h3>League Creation</h3>
                    <p>Create and manage multiple leagues with different sports, seasons, and rules. Full tournament scheduling capabilities.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">⚽</div>
                    <h3>Team Management</h3>
                    <p>Team registration, roster management, and player recruitment. Track team performance and statistics.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Match Scheduling</h3>
                    <p>Automated match scheduling with venue assignment. Record results and update standings automatically.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Statistics & Reports</h3>
                    <p>Comprehensive statistics tracking for players and teams. Generate detailed reports and analytics.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>Communication</h3>
                    <p>Built-in notification system for announcements, schedule changes, and important updates.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">System Capabilities</h2>
            <p style="font-size: 1.1rem; opacity: 0.9;">Built to handle leagues of any size</p>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>∞</h3>
                    <p>Unlimited Leagues</p>
                </div>
                <div class="stat-item">
                    <h3>∞</h3>
                    <p>Unlimited Teams</p>
                </div>
                <div class="stat-item">
                    <h3>∞</h3>
                    <p>Unlimited Players</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>System Availability</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; Web-Based Sports League Registration System. Built with PHP & MySQL.</p>
              <p>- Created by ZyCode</p>
            <p style="margin-top: 1rem; opacity: 0.7;">
                <a href="../auth/login.php" style="color: #007bff; text-decoration: none;">Login</a> | 
                <a href="../auth/register.php" style="color: #007bff; text-decoration: none;">Register</a> | 
                <a href="#features" style="color: #007bff; text-decoration: none;">Features</a>
            </p>
        </div>
    </footer>
</body>
</html>

<?php
/* 
=======================================================================
INSTALLATION GUIDE - Sports League Management System
=======================================================================

SYSTEM REQUIREMENTS:
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- PDO MySQL extension enabled

INSTALLATION STEPS:

1. DATABASE SETUP:
   - Create a new MySQL database named 'sports_league'
   - Import the SQL schema from 'sports_league_database' artifact
   - Update database credentials in config/database.php:
     * $host = 'localhost' (or your host)
     * $db_name = 'sports_league'
     * $username = 'your_username'
     * $password = 'your_password'

2. FILE STRUCTURE:
   Create the following directory structure:
   
   sports_league/
   ├── index.php (main landing page)
   ├── config/
   │   └── database.php (database configuration)
   ├── auth/
   │   ├── login.php
   │   ├── register.php
   │   └── logout.php
   ├── dashboard.php (main dashboard)
   ├── league/
   │   ├── create_league.php
   │   ├── manage_leagues.php
   │   ├── view_league.php
   │   └── browse_leagues.php
   ├── team/
   │   ├── create_team.php
   │   ├── manage_team.php
   │   ├── join_team.php
   │   └── process_request.php
   ├── match/
   │   ├── schedule_matches.php
   │   └── record_result.php
   ├── admin/
   │   ├── manage_users.php
   │   └── system_reports.php
   └── venue/
       └── manage_venues.php

3. FILE DEPLOYMENT:
   - Extract all PHP code from the artifacts
   - Place files in appropriate directories as shown above
   - Ensure web server has read/write permissions

4. DEFAULT ADMIN ACCOUNT:
   - Username: admin
   - Email: admin@sportsleague.com  
   - Password: admin123
   
   IMPORTANT: Change this password immediately after installation!

5. CONFIGURATION:
   - Update config/database.php with your database credentials
   - Modify timezone settings if needed
   - Configure any additional settings as required

6. TESTING:
   - Visit your domain/index.php
   - Test registration and login functionality
   - Create a test league and team
   - Verify all features are working

SECURITY NOTES:
- All passwords are hashed using PHP's password_hash()
- SQL injection protection via prepared statements
- Session-based authentication
- Role-based access control implemented

FEATURES INCLUDED:
✓ User registration and authentication
✓ Role-based access (Admin, Team Owner, Player)
✓ League creation and management
✓ Team registration and roster management
✓ Match scheduling with venue assignment
✓ Results recording and standings calculation
✓ Statistics tracking
✓ Notification system
✓ Admin dashboard with reports
✓ Responsive web design

ADDITIONAL FEATURES TO IMPLEMENT:
- Email notifications
- File upload for team logos
- Advanced statistics and charts
- Payment integration for league fees
- Mobile app API endpoints
- Social media integration

CUSTOMIZATION:
- Modify CSS styles in each PHP file
- Add custom sports in the database
- Extend user profiles with additional fields
- Add custom scoring systems per sport
- Implement playoff/tournament brackets

TROUBLESHOOTING:

Common Issues & Solutions:

1. Database Connection Error:
   - Verify MySQL service is running
   - Check database credentials in config/database.php
   - Ensure database 'sports_league' exists
   - Verify PHP PDO extension is installed

2. Permission Denied Errors:
   - Set proper file permissions (755 for directories, 644 for files)
   - Ensure web server user has access to files
   - Check PHP error logs for specific issues

3. Session Issues:
   - Ensure session.save_path is writable
   - Check PHP session configuration
   - Clear browser cookies if needed

4. Login Not Working:
   - Verify user exists in database
   - Check password hashing (use default admin account first)
   - Enable PHP error reporting during testing

5. Styling Issues:
   - CSS is embedded in PHP files for simplicity
   - Check for syntax errors in style tags
   - Ensure proper HTML structure

DEVELOPMENT NOTES:

This system uses a simple architecture for easy understanding:
- Embedded CSS for quick styling without external files
- Procedural PHP mixed with basic OOP for database class
- Direct SQL queries with PDO for database operations
- Session-based authentication
- No external frameworks for minimal dependencies

For Production Use:
- Implement proper error logging
- Add HTTPS/SSL encryption  
- Set up regular database backups
- Add input validation and sanitization
- Implement rate limiting for login attempts
- Add CSRF protection for forms
- Consider using environment variables for sensitive config

SUPPORT:
- Check PHP error logs in your server's error log directory
- Enable error reporting during development: ini_set('display_errors', 1)
- Test each component individually if issues arise
- Verify database schema matches the provided SQL

=======================================================================
*/
?>