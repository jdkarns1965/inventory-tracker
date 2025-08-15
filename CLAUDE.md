# Inventory Tracker for Plastic Injection Molding - CLAUDE.md

## Project Overview
Build a compact LAMP stack web application for tracking inventory of plastic injection molded parts, including raw materials, components, and packaging. The system must handle two product types: "shoot and ship" parts and "value-added" parts that require assembly before packaging.

## Core Requirements

### Database Schema
Create a MySQL database with the following tables:

**users** - User authentication and roles
- user_id (PRIMARY KEY, AUTO_INCREMENT)
- username (VARCHAR(50), UNIQUE, NOT NULL)
- password_hash (VARCHAR(255), NOT NULL) // bcrypt hashed password
- role (ENUM: 'admin', 'user')
- full_name (VARCHAR(255))
- last_login (TIMESTAMP, NULL)
- created_date (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
- active (BOOLEAN, DEFAULT TRUE)

**permissions** - Available system permissions
- permission_id (PRIMARY KEY, AUTO_INCREMENT)
- permission_name (VARCHAR(50), UNIQUE, NOT NULL)
- permission_description (VARCHAR(255))
- category (VARCHAR(50)) // e.g., 'inventory', 'production', 'management'

**user_permissions** - Junction table for user-specific permissions
- user_id (INT, FOREIGN KEY)
- permission_id (INT, FOREIGN KEY)
- granted_by (INT, FOREIGN KEY) // Which admin granted this permission
- granted_date (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
- PRIMARY KEY (user_id, permission_id)

**parts** - Main part number registry
- part_id (PRIMARY KEY, AUTO_INCREMENT)
- part_number (VARCHAR(50), UNIQUE, NOT NULL)
- part_name (VARCHAR(255))
- part_type (ENUM: 'shoot_ship', 'value_added')
- description (TEXT)
- reorder_point (INT, DEFAULT 0)
- current_stock (INT, DEFAULT 0)
- created_date (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

**molds** - Basic mold data for production calculations
- mold_id (PRIMARY KEY, AUTO_INCREMENT)
- mold_number (VARCHAR(50), UNIQUE, NOT NULL) // Can be same as part number
- total_cavities (INT, NOT NULL) // Total number of cavities in the mold
- shot_size (DECIMAL(8,4), NOT NULL) // Material amount per shot (lbs/kg)
- shot_size_unit (VARCHAR(10), DEFAULT 'lbs') // Unit of measure for shot size
- notes (TEXT)

**mold_cavities** - Define what each cavity produces
- cavity_id (PRIMARY KEY, AUTO_INCREMENT)
- mold_id (INT, FOREIGN KEY)
- cavity_number (INT) // Which physical cavity (1, 2, 3, etc.)
- part_id (INT, FOREIGN KEY) // Which part this cavity produces
- parts_per_shot (INT, DEFAULT 1) // How many of this part per shot cycle
- PRIMARY KEY (mold_id, cavity_number)

**materials** - Raw materials for injection molding
- material_id (PRIMARY KEY, AUTO_INCREMENT)
- material_name (VARCHAR(255), NOT NULL)
- material_type (VARCHAR(100)) // e.g., 'Resin', 'Colorant', 'Additive'
- supplier (VARCHAR(255))
- lead_time_days (INT)
- current_stock (DECIMAL(10,2))
- unit_of_measure (VARCHAR(20)) // e.g., 'lbs', 'kg', 'gallons'
- reorder_point (DECIMAL(10,2))

**components** - Additional components for value-added parts
- component_id (PRIMARY KEY, AUTO_INCREMENT)
- component_name (VARCHAR(255), NOT NULL)
- component_type (VARCHAR(100)) // e.g., 'Hardware', 'Electronics', 'Labels', 'Gasket'
- supplier (VARCHAR(255))
- lead_time_days (INT)
- current_stock (INT)
- reorder_point (INT)

**consumables** - Supplies used in assembly process
- consumable_id (PRIMARY KEY, AUTO_INCREMENT)
- consumable_name (VARCHAR(255), NOT NULL)
- consumable_type (VARCHAR(100)) // e.g., 'Promoter', 'Adhesive', 'Cleaner', 'Lubricant'
- supplier (VARCHAR(255))
- lead_time_days (INT)
- current_stock (INT) // Number of containers
- container_size (VARCHAR(50)) // e.g., '1 gallon', '5 liter', '32 oz'
- reorder_point (INT) // Number of containers

**packaging** - Packaging materials
- packaging_id (PRIMARY KEY, AUTO_INCREMENT)
- packaging_name (VARCHAR(255), NOT NULL)
- packaging_type (VARCHAR(100)) // e.g., 'Box', 'Bag', 'Insert', 'Label'
- supplier (VARCHAR(255))
- lead_time_days (INT)
- current_stock (INT)
- reorder_point (INT)

**part_materials** - Junction table for part-to-material relationships
- part_id (INT, FOREIGN KEY)
- material_id (INT, FOREIGN KEY)
- quantity_per_part (DECIMAL(10,4)) // Amount of material per part
- PRIMARY KEY (part_id, material_id)

**part_components** - Junction table for part-to-component relationships (value-added parts only)
- part_id (INT, FOREIGN KEY)
- component_id (INT, FOREIGN KEY)
- quantity_per_part (INT)
- PRIMARY KEY (part_id, component_id)

**part_consumables** - Junction table for part-to-consumable relationships
- part_id (INT, FOREIGN KEY)
- consumable_id (INT, FOREIGN KEY)
- required (BOOLEAN, DEFAULT TRUE) // Whether this consumable is needed for this part
- application_step (INT) // Order of application (1=first, 2=second, etc.)
- notes (TEXT) // Special instructions
- PRIMARY KEY (part_id, consumable_id)

**part_packaging** - Junction table for part-to-packaging relationships
- part_id (INT, FOREIGN KEY)
- packaging_id (INT, FOREIGN KEY)
- quantity_per_part (INT)
- PRIMARY KEY (part_id, packaging_id)

**inventory_transactions** - Track all inventory movements
- transaction_id (PRIMARY KEY, AUTO_INCREMENT)
- transaction_type (ENUM: 'material', 'component', 'packaging', 'consumable', 'finished_part')
- item_id (INT) // References appropriate table based on type
- transaction_action (ENUM: 'physical_count', 'received', 'used', 'adjust')
- new_quantity (INT) // Updated count after physical check (containers for consumables)
- notes (TEXT) // Notes about the count/adjustment
- transaction_date (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

### Core Features to Implement

#### 1. Authentication System
- **Login Page**: Clean, mobile-friendly login form
- **Session Management**: Secure PHP sessions with timeout
- **Granular Permissions**: Custom permission sets per user
- **Auto-logout**: Session timeout for security
- **Remember Login**: Optional "keep me logged in" for mobile convenience

#### 2. User Administration (admin.php) - Admins Only
- **User Management**: Create, edit, deactivate users
- **Permission Assignment**: Grant/revoke specific permissions per user
- **Permission Categories**: Organized by function (inventory, production, etc.)
- **Bulk Permission Sets**: Pre-defined permission groups for common roles
- **User Activity**: View last login dates and activity
- **Password Reset**: Force password change for any user

#### 3. Dashboard (index.php)
- **Mobile Dashboard**: Card-based layout optimized for phone screens
- **Key Metrics at a Glance**: Large, easy-to-read numbers for stock levels
- **Color-Coded Alerts**: Visual indicators for low stock items
- **Quick Action Buttons**: Large, thumb-friendly buttons for common tasks
- **Swipeable Sections**: Horizontal swipe between different inventory categories

#### 4. Mold Management (molds.php)
- Add/edit/view mold information
- **Simple Setup**: Mold number (can match part number), cavities, shot size
- Calculate material usage for production planning

#### 5. Parts Management (parts.php)
- Add/edit/view part numbers
- View which molds can produce each part
- Define part type (shoot & ship vs value-added)
- Set reorder points for finished goods
- View complete bill of materials for each part

#### 6. Materials Management (materials.php)
- Add/edit/view raw materials
- Track current stock levels
- Manage supplier information and lead times
- Record material receipts and usage

#### 7. Components Management (components.php)
- Add/edit/view components for value-added parts
- Track inventory levels
- Manage supplier info and lead times

#### 8. Consumables Management (consumables.php)
- Add/edit/view assembly consumables (promoters, adhesives, cleaners)
- **Container-Based Tracking**: Track number of containers (gallons, bottles, etc.)
- Track inventory levels by container count
- Manage supplier relationships and lead times
- Simple reorder alerts when container count is low

#### 9. Packaging Management (packaging.php)
- Add/edit/view packaging materials
- Track inventory levels
- Manage supplier relationships

#### 10. Bill of Materials (BOM) Builder (bom.php)
- Assign materials, components, consumables, and packaging to specific parts
- **Assembly Process Mapping**: Define step-by-step assembly sequence
- View mold information and cavitation details
- Calculate material usage based on shot size and cavities
- **Complete Process View**: See entire process from molding through final packaging
- **Example Process for Part 20638**:
  1. Mold base part (plastic material)
  2. Apply promoter (consumable)
  3. Assemble component 20636
  4. Package finished assembly

#### 11. Physical Inventory Updates (inventory.php)
- **One-Handed Data Entry**: Large number pad, auto-focus on quantity fields
- **Voice Input Support**: Speech-to-text for hands-free quantity entry
- **Barcode Scanner Ready**: Camera-based scanning preparation for future enhancement
- **Batch Update Mode**: Select multiple items and update quantities in sequence
- **Offline Capability**: Store updates locally when connection is poor, sync when back online
- **Quick Corrections**: Easy undo/redo for entry mistakes

#### 12. Inventory Status (status.php)
- **Current Stock Levels**: Real-time view of all inventory items
- **Last Count Dates**: When each item was last physically verified
- **Stock Age Indicators**: Visual indicators for items not counted recently
- **Low Stock Alerts**: Items at or below reorder points
- **Container Status**: Simple tracking for promoter gallons and other consumable containers

#### 13. Reorder Management (reorder.php)
- View all items at or below reorder points
- Sort by lead time for procurement planning
- **Simple Container Reordering**: Alert when promoter containers get low
- Generate purchase recommendations
- Export reorder lists

#### 14. Production Planning (production.php)
- **Touch-Friendly Calculator Interface**: Large number inputs with built-in calculator
- **Visual Material Gauge**: Progress bars showing material consumption vs available stock
- **Production Scenarios**: "What if" calculations with slider inputs
- **Results Summary Cards**: Clear, scannable output with key numbers highlighted

### Technical Specifications

#### Frontend Requirements
- **Mobile-First Design**: Optimized primarily for phone use with touch-friendly interface
- **Modern UI Framework**: Bootstrap 5 with contemporary styling and components
- **Large Touch Targets**: Buttons and input fields sized for easy finger navigation
- **Clean Typography**: Clear, readable fonts at appropriate sizes for mobile screens
- **Intuitive Navigation**: Simple, obvious menu structure with minimal taps to reach any function
- **Fast Loading**: Lightweight pages that load quickly on mobile connections
- **Progressive Web App Features**: Add to home screen capability, works offline for basic viewing
- **Dark/Light Mode**: Modern theme switching for different lighting conditions
- **Responsive Tables**: Mobile-optimized data display with horizontal scrolling and collapsible columns
- **Quick Actions**: Swipe gestures and long-press menus where appropriate

#### Backend Requirements
- PHP 8+ with MySQLi or PDO
- **Secure Authentication**: bcrypt password hashing, secure session handling
- **Granular Permissions**: Check specific permissions for each action
- **CSRF Protection**: Prevent cross-site request forgery attacks
- **Input Validation**: Server-side validation and sanitization
- **Session Security**: Regenerate session IDs, secure cookie settings
- **SQL Injection Prevention**: Prepared statements for all database queries
- **Permission Caching**: Cache user permissions in session for performance
- Error logging and user-friendly error messages

#### File Structure
```
/inventory-tracker/
├── config/
│   ├── database.php (DB connection settings)
│   ├── config.php (App configuration)
│   └── auth.php (Authentication functions)
├── includes/
│   ├── header.php (Common HTML head, navigation)
│   ├── footer.php (Common footer)
│   ├── functions.php (Utility functions)
│   └── auth_check.php (Role-based access control)
├── css/
│   └── custom.css (Custom styles)
├── js/
│   └── app.js (JavaScript functionality)
├── login.php (Authentication page)
├── logout.php (Logout handler)
├── admin.php (User and permission management - Admins only)
├── index.php (Dashboard)
├── molds.php
├── parts.php
├── materials.php
├── components.php
├── consumables.php
├── packaging.php
├── bom.php
├── status.php
├── inventory.php
├── reorder.php
├── production.php
└── install.php (Database setup script with default users)
```

### Key Calculations to Implement

#### Production Calculations
```php
// Simple calculation: How many parts can I make?
function calculateMaxParts($mold_id, $available_material) {
    $mold = getMoldInfo($mold_id);
    $max_shots = floor($available_material / $mold['shot_size']);
    $max_parts = $max_shots * $mold['total_cavities'];
    return $max_parts;
}

// Simple calculation: How much material do I need?
function calculateMaterialNeeded($mold_id, $target_parts) {
    $mold = getMoldInfo($mold_id);
    $shots_needed = ceil($target_parts / $mold['total_cavities']);
    $material_needed = $shots_needed * $mold['shot_size'];
    return $material_needed;
}

// Example:
// Current stock: 500 lbs of resin
// Mold: 4 cavities, 2.5 lbs per shot
// Question: "How many parts can I make?"
// Answer: 500 ÷ 2.5 = 200 shots × 4 cavities = 800 parts
```

### User Roles and Permissions System

#### Administrator Role
- **Full System Access**: Can access all features and data
- **User Management**: Can create, edit, and deactivate users
- **Permission Management**: Can grant/revoke any permission to any user
- **System Configuration**: Can modify all settings and configurations

#### Custom User Permissions (Granular)
Users can be granted specific permissions in these categories:

**Inventory Management Permissions:**
- `view_inventory` - View current stock levels and inventory data
- `update_inventory` - Perform physical counts and inventory adjustments
- `manage_materials` - Add/edit raw materials
- `manage_components` - Add/edit components
- `manage_consumables` - Add/edit consumables (promoters, adhesives, etc.)
- `manage_packaging` - Add/edit packaging materials

**Parts & Production Permissions:**
- `view_parts` - View parts information and BOMs
- `manage_parts` - Add/edit parts and their specifications
- `view_molds` - View mold information
- `manage_molds` - Add/edit mold specifications
- `production_planning` - Access production calculation tools
- `view_bom` - View bills of materials
- `manage_bom` - Create/edit bills of materials

**Reporting & Analysis Permissions:**
- `view_reports` - Access inventory status and reports
- `reorder_management` - View and manage reorder lists
- `export_data` - Export reports and data
- `view_transactions` - View inventory transaction history

**System Permissions:**
- `admin_panel` - Access user administration (Admins only)
- `system_settings` - Modify system configuration

#### Permission Groups (Pre-defined Sets)
**Read-Only User:**
- `view_inventory`, `view_parts`, `view_molds`, `view_reports`, `view_bom`

**Inventory Clerk:**
- All Read-Only permissions plus: `update_inventory`, `reorder_management`

**Production Planner:**
- All Read-Only permissions plus: `production_planning`, `manage_bom`

**Supervisor:**
- All permissions except: `admin_panel`, `system_settings`

**Custom:** 
- Admin can select any combination of permissions

#### Security Features
- **Automatic Logout**: Sessions expire after 4 hours of inactivity
- **Secure Passwords**: Minimum 8 characters, bcrypt hashed storage
- **HTTPS Ready**: Designed for SSL/TLS deployment
- **Session Protection**: Secure session cookies, CSRF tokens
- **Failed Login Protection**: Basic rate limiting (can enhance later)

### User Interface Guidelines
1. **Mobile-First Design Philosophy**: Design for phone first, then enhance for larger screens
2. **Touch-Optimized Forms**: Large input fields (minimum 44px height), generous spacing, number keyboards for quantities
3. **Thumb-Friendly Navigation**: Bottom navigation bar or easily reachable top menu
4. **Visual Hierarchy**: Clear headings, proper contrast, logical information flow
5. **Instant Feedback**: Visual confirmation for all actions, loading states, success/error messages
6. **Minimal Scrolling**: Key information visible without scrolling, compact but readable layouts
7. **Quick Entry Patterns**: Auto-focus on relevant fields, smart defaults, one-handed operation support
8. **Modern Aesthetics**: 
   - Clean card-based layouts
   - Subtle shadows and rounded corners
   - Contemporary color schemes
   - Smooth transitions and micro-animations
   - Modern iconography (Feather icons or similar)
9. **Accessibility**: Proper color contrast, readable font sizes, clear focus indicators

### Mobile-Specific Features to Implement

#### Touch Interactions
- **Tap to Edit**: Single tap on any quantity to edit inline
- **Pull to Refresh**: Standard mobile gesture to update data
- **Swipe Actions**: Swipe left/right for quick actions (mark counted, add to reorder list)
- **Long Press Menus**: Context menus for additional options

#### Mobile Performance
- **Lazy Loading**: Load content as needed to keep app fast
- **Image Optimization**: Compress any images for mobile bandwidth
- **Caching Strategy**: Cache frequently accessed data for offline viewing
- **Touch Feedback**: Haptic feedback and visual responses to touches

#### Mobile UX Patterns
- **Bottom Navigation**: Keep primary navigation within thumb reach
- **Floating Action Button**: Quick access to most common action (add inventory count)
- **Progressive Disclosure**: Show essential info first, details on demand
- **Search and Filter**: Easy-to-use search with autocomplete for finding parts quickly

### Key Permission Checking Functions

```php
// Check if user has specific permission
function hasPermission($user_id, $permission_name) {
    // Check if user is admin (admins have all permissions)
    if (isAdmin($user_id)) return true;
    
    // Check user_permissions table
    $sql = "SELECT COUNT(*) FROM user_permissions up 
            JOIN permissions p ON up.permission_id = p.permission_id 
            WHERE up.user_id = ? AND p.permission_name = ?";
    // Return true/false
}

// Get all permissions for a user (cache in session)
function getUserPermissions($user_id) {
    if (isAdmin($user_id)) {
        return getAllPermissions(); // Admins get everything
    }
    
    $sql = "SELECT p.permission_name FROM user_permissions up 
            JOIN permissions p ON up.permission_id = p.permission_id 
            WHERE up.user_id = ?";
    // Return array of permission names
}

// Admin User Management Functions
function createUser($username, $password, $full_name, $permissions = []) {
    // Hash password, insert user, assign permissions
}

function grantPermission($user_id, $permission_name, $granted_by_admin_id) {
    // Add permission to user_permissions table
}

function revokePermission($user_id, $permission_name) {
    // Remove permission from user_permissions table
}
```

### Installation Instructions
Include an install.php script that:
1. Creates the database schema
2. **Creates default admin user**: username 'admin', password 'admin123' (full admin access)
3. **Sets up permission system**: Inserts all available permissions into permissions table
4. **Creates sample users**: 
   - 'inventory_clerk' with basic inventory permissions
   - 'supervisor' with most permissions except admin functions
5. Inserts sample data for testing
6. Sets up basic configuration
7. **Security Setup Reminder**: Prompts to change default passwords immediately
8. Provides setup completion confirmation

### Security Considerations
- **Password Security**: bcrypt hashing with proper salt, minimum password requirements
- **Session Security**: Secure session cookies, session regeneration, timeout handling
- **Input Sanitization**: Sanitize all user inputs, validate on both client and server
- **SQL Injection Prevention**: Use prepared statements for all database queries
- **CSRF Protection**: Implement tokens for state-changing operations
- **Permission-Based Access**: Check specific permissions for every action, not just page access
- **Admin Protection**: Ensure only true admins can access user management functions
- **Permission Caching**: Cache user permissions in session, refresh on permission changes
- **HTTPS Deployment**: Design assumes SSL/TLS in production environment
- **Error Handling**: Don't expose sensitive information in error messages
- **File Security**: Proper file permissions, no direct access to config files

### Future Enhancement Hooks
- User authentication system
- Barcode scanning integration
- API endpoints for mobile apps
- Advanced reporting and analytics
- Integration with ERP systems

## Success Criteria
The application should allow a manufacturing manager to:
1. **Efficiently use on mobile phone** during inventory walks and daily operations
2. **Quickly update stock levels** with large, touch-friendly interfaces
3. **View critical information at a glance** with modern, scannable layouts
4. **Navigate intuitively** without training or referring to documentation
5. **Work reliably in manufacturing environment** with offline capabilities and fast loading
6. **Calculate production requirements** with easy-to-use mobile calculators
7. **Get instant alerts** for reorder needs with clear visual indicators

**Design Goal**: Create a modern, polished inventory management tool that feels as intuitive as using a well-designed mobile app, replacing paper clipboards and Excel spreadsheets with a superior digital experience optimized for manufacturing floor use.

Build this as a focused, efficient tool that solves the specific inventory challenges of a plastic injection molding operation.