Internal Blood Bank Management System (IBBMS)
The IBBMS is a localized web application designed to digitize manual blood bank records exclusively within a single hospital's intranet. This system was developed as a 13-lab academic project for Manipal University Jaipur by Akshat Singh and Tanishka Srivastava.

Tech Stack & Environment
Frontend: HTML, CSS, JavaScript.

Backend: PHP.

Database: MySQL.

Server Environment: XAMPP (Localhost Server).

Core Features & User Roles
Donor Registration: Captures donor details (Name, Blood Group, Contact) and automatically generates a unique Donor Card Number upon registration.

Inventory Management: Tracks real-time aggregate blood stock, automatically incrementing units upon donation and deducting them when hospital requests are accepted.

Internal Blood Requests: Allows internal medical staff (wards/doctors) to securely request specific blood groups and quantities based on real-time availability.

Security: Implements hashed user passwords and SQL injection prevention techniques for secure role-based access.

Admin Role: The overarching manager who oversees the entire blood bank, manages user/staff accounts, generates detailed inventory reports, and can approve or reject blood requests.

Lab Staff Role: Internal workers tasked with registering donors, recording donations, managing stock, and updating the status of internal blood requests.

Internal Medical Staff Role: Doctors and nurses who log in to search for available blood types and submit requests for their patients.

Local Installation Setup
Download and install a local cross-platform web server solution like XAMPP.

Clone this repository and place the project folder into your local server's web directory (e.g., the htdocs folder for XAMPP).

Launch the XAMPP Control Panel and start the Apache and MySQL modules.

Open phpMyAdmin in your browser and import the provided .sql file to generate the required normalized database schema.

Access the application by navigating to the local project URL in a standard web browser like Chrome or Edge.