# Aquanest - Water Delivery Service

A modern water delivery service management system built with PHP, MySQL, and Tailwind CSS.

## Features

- Product ordering system with shopping cart
- Real-time order tracking
- Subscription management
- Multiple payment methods (COD, Bank Transfer, QRIS)
- Courier assignment and tracking
- Order history
- Responsive design

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite enabled
- Composer (for dependencies)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/aquanest.git
cd aquanest
```

2. Create a MySQL database and import the schema:
```bash
mysql -u root -p
CREATE DATABASE aquanest;
exit;
mysql -u root -p aquanest < database/aquanest.sql
```

3. Configure the database connection:
Edit `includes/config.php` and update the database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'aquanest');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

4. Set up the upload directory:
```bash
mkdir uploads
chmod 777 uploads
```

5. Configure your web server:
Make sure the document root points to the project directory and mod_rewrite is enabled.

## Directory Structure

```
aquanest/
├── includes/
│   ├── config.php
│   ├── db.php
│   └── functions.php
├── database/
│   └── aquanest.sql
├── uploads/
├── index.php
├── order.php
└── .htaccess
```

## Usage

1. Access the application through your web browser:
```
http://localhost/aquanest/
```

2. Available pages:
- Order: Browse and order water products
- Track: Track order status and delivery
- Subscription: Manage water delivery subscriptions
- History: View order history

## Security

- Input validation and sanitization
- PDO prepared statements for database queries
- XSS protection
- CSRF protection
- Secure file upload handling
- Protected sensitive files through .htaccess

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
