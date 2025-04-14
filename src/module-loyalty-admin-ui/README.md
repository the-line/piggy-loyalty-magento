# Leat LoyaltyAdminUI Module

## Overview
The LeatAdminUI module provides admin interfaces and backend functionality for the Leat loyalty and rewards system. It extends Magento's admin area with Leat-specific features and configurations.

## Features

### Data Synchronization System

#### Sync Data Interface
- Provides a unified system configuration interface for data synchronization with Leat
- Uses a modular, extensible design for adding additional sync operations beyond attributes
- Real-time validation to ensure required data structures exist at Leat
- Direct API validation instead of flag-based status tracking for data integrity

#### Validation Framework
- Built-in validation system to check if required attributes exist in Leat
- Status is displayed directly in the admin UI with appropriate styling
- Supports multi-store configuration with store-specific validation
- Extensible architecture for adding new validation rules

#### Technical Details
- Direct connection testing without dependency on cached flags
- Live attribute validation through AttributeResource
- Clean separation of concerns between Sync UI and validation logic
- Consistent status reporting with color-coded messages

### Connection Testing System
- Provides real-time connection status checking for Leat API
- Direct API validation without relying on cached flag data
- Clear status reporting with appropriate styling based on connection state
- Store-specific configuration support

### Coupon Usage Plugin

#### UsagePlugin for SalesRule Coupon
- Intercepts Magento's standard coupon usage tracking
- Allows Leat reward coupons to use a custom tracking mechanism
- Properly marks Leat coupons as collected when used in orders
- Integrates with the AppliedCouponsManager from the core Leat module

#### Technical Details
- Uses the `aroundUpdateCustomerRulesUsages` plugin method to intercept Magento's core coupon usage tracking
- Identifies Leat coupons by checking if they exist in the applied coupons data
- Provides proper error handling and logging
- Maintains compatibility with Magento's standard coupon system for non-Leat coupons

### Gift Product Cart Price Rule UI

#### Custom Action Type
- Adds "Add gift products to cart" to the available cart price rule actions
- Provides a field for specifying comma-separated product SKUs
- Handles visibility of fields based on selected action type
- Supports both simple products and configurable product variants
- Integrates with standard discount_qty field to limit gift quantities
- Uses discount_amount field for percentage-based gift discounts (0-100%)

#### Extension Attribute Implementation
- Implements extension attributes for SalesRule entity
- Stores gift_skus in a dedicated database table (leat_loyalty_salesrule_extension)
- Persists and loads the gift SKUs field across page loads and rule edits
- Uses model-resource-repository pattern for clean data access
- Provides reliable persistence even with complex form submissions

#### Rule Form Integration
- Properly loads extension attribute data into the admin form
- Maintains data integrity through the entire edit-save lifecycle
- Works seamlessly with Magento's validation system
- Handles both new rule creation and existing rule editing

#### Extension-Friendly Implementation
- Uses non-invasive JavaScript to enhance the sales rule form
- Compatible with other extensions that customize the same form
- Follows Magento best practices for UI customizations
- Properly handles all field visibility states
- Respects core Magento fields (discount_qty, discount_amount) for consistent behavior

#### Technical Details
- Uses jQuery event listeners to detect form field changes
- Dynamically shows/hides the gift SKUs field based on selected action
- Supports both initial page load and subsequent form changes
- Implements a clean, maintainable approach that doesn't override core components
- Persists extension attributes with plugins for rule repository

### Leat Coupon Type
- Adds a "Leat Coupon" type to the standard Magento coupon types
- Shows/hides appropriate fields based on the selected coupon type
- Integrates with the core Leat reward system

## Architecture
The module follows a clean architecture that extends Magento's admin functionality without overriding core components:

- **Plugins** to extend existing functionality
- **UI Components** to add new form fields
- **JavaScript** for dynamic behavior
- **Templates** for custom rendering
- **XML Layouts** for structural changes
- **Service Layer** with validation classes for business logic encapsulation

All implementations are designed to work alongside other extensions and maintain compatibility with future Magento updates.
