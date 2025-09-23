# Training Session Booking System

This system allows users to book training sessions on various topics. It's built using PHP and MySQL, with a simple HTML frontend.

## Features

- Display available workshops with their descriptions, timings, and capacities
- Allow users to book sessions for available workshops
- Prevent overbooking by tracking available slots
- Display booking information for all registered sessions

## SetUp

1. Ensure you have PHP and MySQL installed on your server.
2. Create a MySQL database named "Training".
3. Update the database connection details in `db_config.php` if necessary.
4. Place all files in your web server's document root.
5. Access the system through your web browser.

## File Structure

- `index1.html`: Main HTML template for the booking system
- `db_config.php`: Database configuration and initial setup
- `index.php`: Main PHP file handling form submissions and data display

## Database Structure

The system uses two main tables:

1. `bookings`: Stores information about booked sessions
2. `trainings`: Stores information about available training sessions

## Usage

1. Users can view available workshops on the main page.
2. To book a session, users select a topic and available time slot.
3. Users enter their name and email to complete the booking.
4. The system prevents duplicate bookings and overbooking.
5. Booked sessions are displayed at the bottom of the page.

## Error Handling

- The system validates user input for name and email format.
- It checks for available slots before confirming a booking.
- Error messages are displayed for invalid inputs or when sessions are full.

## Maintenance

- To add or modify training sessions, update the `trainings` table in the database.
- The system automatically populates the `trainings` table with default data if it's empty.

## Security Considerations

- The system uses prepared statements to prevent SQL injection.
- It implements basic input validation for user-submitted data.
- Consider implementing additional security measures for a production environment.

## Future Improvements

- Add user authentication
- Implement a cancellation feature for bookings
- Create an admin panel for managing training sessions
- Enhance the user interface with CSS and JavaScript

For any issues or suggestions, please contact the system administrator.
