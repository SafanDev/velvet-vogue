# Velvet Vogue

Velvet Vogue is a fashion e-commerce web application.

The system includes a customer shopping experience and an administrator dashboard for managing products, categories, users, orders, coupons, reviews, inquiries, and store settings.

## Features

* Customer registration and login
* Product catalogue with search and filters
* Product variants including size, colour, price, and stock
* Shopping cart and wishlist
* Coupon discounts
* Cash-on-delivery checkout
* Order history, invoices, and tracking
* Customer reviews and inquiries
* Customer profile and address management
* Administrator dashboard
* Product and category management
* Order and customer management
* Responsive design
* Custom 404 page

## Technologies

* PHP
* MySQL
* HTML5
* CSS3
* JavaScript
* Bootstrap

## Local Setup

1. Install XAMPP or another PHP and MySQL environment.
2. Copy the project into the web server directory.
3. Create a MySQL database.
4. Import the Velvet Vogue database.
5. Copy `.env.example` and rename the copy to `.env`.
6. Update the database settings inside `.env`.
7. Start Apache and MySQL.
8. Open the project through `localhost`.

Example local address:

```text
http://localhost/Project/velvet-vogue-main/
```

## Environment Configuration

The application requires a `.env` file containing the application URL, application key, and database connection details.


## Testing

The project includes the following scripts:

```bash
php scripts/security-self-test.php
php scripts/preflight.php
```


## Payment

The current portfolio version supports cash on delivery. Online card payments are not enabled.

## Project Purpose

This project was created for educational use. The products, users, images, and order information included in the demonstration database are fictional.
