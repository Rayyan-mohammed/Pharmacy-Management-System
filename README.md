# Pharmacy Management System

A comprehensive web-based solution for managing pharmacy operations, inventory, sales, and reporting.

## Features

- **User Authentication System**: Secure login and registration with role-based access control
- **Dashboard**: Intuitive dashboard with quick access to all main functions
- **Inventory Management**:
  - Add new medicines
  - Update stock levels
  - Check current inventory
  - Generate inventory reports
  - Expiration date tracking
- **Sales Management**:
  - Process medicine sales
  - Record and track sales history
  - View sales reports
- **Analytics and Reporting**:
  - Top selling medicines analysis
  - Sales statistics and trends
  - Financial reporting
- **Supplier Management**: Track and manage supplier information
- **Prescription Management**: Record and track patient prescriptions

## Technologies Used

- **Frontend**: HTML, CSS, Bootstrap 5, JavaScript
- **Backend**: PHP
- **Database**: MySQL

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/pharmacy-management-system.git
   ```

2. Import the database schema:
   - Find `database.sql` in the `public` directory
   - Create a MySQL database named `medical_management`
   - Import the SQL file into your database

3. Configure database connection:
   - Open `public/database.php`
   - Update the database credentials if necessary

4. Set up a local server:
   - Use XAMPP, WAMP, MAMP, or any PHP server
   - Place the project in your server's web directory
   - Access the application via browser (e.g., `http://localhost/pharmacy-management-system/public`)

## Usage

1. Register a new admin user or use the default credentials
2. Log in to access the dashboard
3. Navigate through the system using the dashboard menu
4. Add medicines, manage inventory, process sales, and view reports

## Contributions

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contact

For any questions or suggestions, please open an issue in this repository. 