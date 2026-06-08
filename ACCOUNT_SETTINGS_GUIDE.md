# Account Settings Feature - Implementation Guide

## Overview
This document describes the Account Settings feature added for Staff and Cashier roles in the PointShift POS System, allowing them to manage their profile, change password, and view assigned shifts.

## Files Created

### 1. `staff/account_settings.php`
Complete account management page for Staff members with:
- Profile information update
- Password change functionality
- Quick view of upcoming shifts
- Links to full shift management

### 2. `cashier/account_settings.php`
Complete account management page for Cashier members with:
- Profile information update
- Password change functionality
- Quick view of upcoming shifts
- Links to full shift management

## Files Modified

### 1. `staff/layout.php`
Added navigation link:
```php
<li class="nav-item">
    <a class="nav-link" href="account_settings.php">
        <i class="fas fa-user-cog"></i>
        Account Settings
    </a>
</li>
```

### 2. `cashier/views/layout.php`
Added navigation link:
```php
<li class="nav-item">
    <a class="nav-link" href="../account_settings.php">
        <i class="fas fa-user-cog"></i>
        Account Settings
    </a>
</li>
```

## Features Implemented

### 1. Profile Settings Tab
**Functionality:**
- Update first name and last name
- Update email address
- View username (read-only)
- View role (read-only)
- Display member since date

**Validation:**
- Email uniqueness check (prevents duplicate emails)
- Required field validation
- Session update after profile change

**Code Example:**
```php
// Update Profile
if ($action === 'update_profile') {
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    // Check if email is already taken
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $current_user_id);
    $check_stmt->execute();
    
    // Update if email is unique
    $stmt = $conn->prepare("UPDATE users SET email=?, first_name=?, last_name=? WHERE id=?");
    $stmt->bind_param("sssi", $email, $first_name, $last_name, $current_user_id);
    $stmt->execute();
}
```

### 2. Change Password Tab
**Functionality:**
- Verify current password
- Set new password
- Confirm new password
- Password strength validation (minimum 6 characters)

**Security Features:**
- Current password verification using password_verify()
- Password confirmation matching
- Password hashing using password_hash()
- Client-side validation with JavaScript
- Server-side validation

**Validation Rules:**
- Current password must be correct
- New password must match confirmation
- New password must be at least 6 characters
- All fields are required

**Code Example:**
```php
// Change Password
if ($action === 'change_password') {
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $message = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
    } else {
        // Hash and update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $update_stmt->bind_param("si", $hashed_password, $current_user_id);
        $update_stmt->execute();
    }
}
```

### 3. My Shifts Tab
**Functionality:**
- Display upcoming 5 shifts
- Show shift details (date, time, location)
- Display assignment status with color-coded badges
- Show supervisor badge if applicable
- Link to full shift view page

**Features:**
- Mini shift cards with hover effects
- Color-coded status badges:
  - **Assigned** - Yellow (warning)
  - **Confirmed** - Green (success)
  - **Declined** - Red (danger)
- Quick access to full shift management
- Responsive design

**Query:**
```sql
SELECT 
    sa.id as assignment_id,
    sa.status as assignment_status,
    sa.role as shift_role,
    s.shift_name,
    s.shift_date,
    s.start_time,
    s.end_time,
    s.location,
    s.status as shift_status
FROM shift_assignments sa
JOIN shifts s ON sa.shift_id = s.id
WHERE sa.user_id = ? 
AND s.shift_date >= CURDATE() 
AND s.status != 'completed'
ORDER BY s.shift_date ASC, s.start_time ASC
LIMIT 5
```

## User Interface Design

### Profile Header
- **Staff:** Purple gradient background (`#667eea` to `#764ba2`)
- **Cashier:** Pink/Yellow gradient background (`#fa709a` to `#fee140`)
- Circular avatar with user initials
- Full name and username display
- Email and role badges
- Member since date

### Tabbed Interface
Three tabs with icons:
1. **Profile Settings** - `fa-user-edit`
2. **Change Password** - `fa-key`
3. **My Shifts** - `fa-calendar-alt`

### Responsive Design
- Mobile-friendly layout
- Collapsible sidebar on small screens
- Stacked forms on mobile devices
- Touch-friendly buttons

## Navigation

### Staff Access
```
Staff Dashboard → Account Settings
URL: /staff/account_settings.php
```

### Cashier Access
```
Cashier Dashboard → Account Settings
URL: /cashier/account_settings.php
```

## User Workflows

### Update Profile Workflow
1. Click "Account Settings" in sidebar
2. Ensure "Profile Settings" tab is active
3. Edit first name, last name, or email
4. Click "Update Profile" button
5. See success message
6. Session automatically updated

### Change Password Workflow
1. Click "Account Settings" in sidebar
2. Click "Change Password" tab
3. Enter current password
4. Enter new password (min 6 chars)
5. Confirm new password
6. Click "Change Password" button
7. See success message
8. Password immediately updated

### View Shifts Workflow
1. Click "Account Settings" in sidebar
2. Click "My Shifts" tab
3. View upcoming 5 shifts
4. See shift details and status
5. Click "View All Shifts" for complete list
6. Click "View All Shifts & Manage Assignments" to confirm/decline

## Security Features

### 1. Authentication
```php
User::requireLogin();

// Check if user is staff/cashier
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
```

### 2. Authorization
- Users can only edit their own profile
- Current user ID from session
- No user_id in form (prevents tampering)

### 3. Input Validation
- Server-side validation for all inputs
- Prepared statements prevent SQL injection
- Password strength validation
- Email format validation

### 4. Password Security
- Current password verification required
- bcrypt hashing algorithm
- Password confirmation matching
- Minimum length requirement

## Form Submissions

### Update Profile
```
POST /staff/account_settings.php
or
POST /cashier/account_settings.php

Parameters:
- action: update_profile
- first_name: string
- last_name: string
- email: string (validated)
```

### Change Password
```
POST /staff/account_settings.php
or
POST /cashier/account_settings.php

Parameters:
- action: change_password
- current_password: string
- new_password: string (min 6 chars)
- confirm_password: string (must match new_password)
```

## Error Handling

### Profile Update Errors
- **Email taken:** "Email is already taken by another user."
- **Database error:** "Error updating profile."

### Password Change Errors
- **Wrong current password:** "Current password is incorrect."
- **Password mismatch:** "New passwords do not match."
- **Too short:** "New password must be at least 6 characters long."
- **Database error:** "Error changing password."

## Success Messages
- **Profile updated:** "Profile updated successfully!" (green alert)
- **Password changed:** "Password changed successfully!" (green alert)

## JavaScript Enhancements

### Password Validation
```javascript
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPass.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});
```

### Tab Persistence
Maintains active tab after form submission:
```javascript
if (window.location.hash) {
    const hash = window.location.hash;
    const tab = document.querySelector(`button[data-bs-target="${hash}"]`);
    if (tab) {
        const bsTab = new bootstrap.Tab(tab);
        bsTab.show();
    }
}
```

## Styling

### CSS Classes
- `.settings-section` - White cards with shadow
- `.profile-header` - Gradient header with avatar
- `.profile-avatar` - Circular initial display
- `.shift-mini-card` - Compact shift display
- `.nav-tabs` - Custom tab styling

### Color Scheme
- **Primary:** Red (#dc3545) - PointShift brand
- **Success:** Green (#198754)
- **Warning:** Yellow (#ffc107)
- **Info:** Blue (#0d6efd)

## Testing Checklist

### Profile Settings
- [ ] Update first name
- [ ] Update last name
- [ ] Update email
- [ ] Try duplicate email (should fail)
- [ ] Verify session updates
- [ ] Check username is read-only
- [ ] Check role is read-only

### Password Change
- [ ] Enter wrong current password (should fail)
- [ ] Enter mismatched passwords (should fail)
- [ ] Enter password < 6 chars (should fail)
- [ ] Successfully change password
- [ ] Login with new password
- [ ] Verify old password doesn't work

### Shifts View
- [ ] View upcoming shifts (if assigned)
- [ ] Check status badges display correctly
- [ ] Check supervisor badge shows when applicable
- [ ] Click "View All Shifts" button
- [ ] Verify proper redirect to view_shifts.php
- [ ] Check empty state message when no shifts

### Navigation
- [ ] Access from staff dashboard
- [ ] Access from cashier dashboard
- [ ] Verify active state highlights
- [ ] Test mobile responsive menu

## Database Tables Used

### `users` Table
```sql
SELECT username, email, first_name, last_name, role, created_at 
FROM users 
WHERE id = ?
```

### `shifts` and `shift_assignments` Tables
```sql
SELECT sa.*, s.*
FROM shift_assignments sa
JOIN shifts s ON sa.shift_id = s.id
WHERE sa.user_id = ?
```

## Browser Compatibility
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers

## Future Enhancements

### Profile Picture
- Upload profile photo
- Crop and resize image
- Display in avatar circle

### Two-Factor Authentication
- Enable 2FA option
- QR code generation
- Backup codes

### Activity Log
- View login history
- Track profile changes
- Password change history

### Notifications Preferences
- Email notifications toggle
- SMS notifications toggle
- Shift reminder settings

### Theme Preferences
- Light/Dark mode
- Color scheme selection
- Font size preferences

## Troubleshooting

### Cannot Access Page
**Issue:** Redirect to login
**Solution:** Ensure user is logged in with correct role

### Email Already Taken
**Issue:** Cannot update email
**Solution:** Choose a different email address

### Password Change Fails
**Issue:** Current password incorrect
**Solution:** Verify current password is correct

### Shifts Not Showing
**Issue:** No shifts displayed
**Solution:** Check if user has been assigned shifts by admin

## Support

For issues or questions:
1. Verify database tables exist
2. Check user session is active
3. Ensure correct role (staff/cashier)
4. Review browser console for errors
5. Check PHP error logs

## Changelog

### Version 1.0.0 (October 10, 2025)
- Initial implementation
- Created account_settings.php for staff
- Created account_settings.php for cashier
- Added navigation links to both layouts
- Implemented profile update functionality
- Implemented password change functionality
- Implemented shifts quick view
- Added form validation (client & server)
- Added security measures
- Created comprehensive documentation
