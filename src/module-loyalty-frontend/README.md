# Leat LoyaltyFrontend Module

## Overview
The LeatFrontend module provides frontend components and widgets for the Leat Loyalty loyalty system in Magento 2. It enables customers to interact with the loyalty program through various UI elements and widgets.

## Recent Changes - UI Improvements

### Progress Button Component
A new reusable JavaScript component that enhances the user experience during asynchronous operations:
- Provides visual feedback with smooth progress bar animation
- Supports toggle behavior for actions that can be reversed (apply/remove)
- Includes customizable states: loading, success, error
- Implements smooth transitions between states with CSS animations
- Maintains consistent button styling across different states

### Your Coupons Widget Improvements
Enhanced UI interaction for coupon/reward redemption with the following improvements:
- Improved error handling and user feedback
- Prevention of duplicate API calls during reloading
- Toggle functionality for applying/removing coupons
- Integration with Magento's messaging system for consistent notifications
- Better visual feedback during asynchronous operations

## Features

### Gift Products Display
Enhances the display and management of gift products added to cart by promotions.

**Key features:**
- Clear visual indicators for gift items in cart
- Shows original price with strikethrough and applied discount
- Properly handles gift item removal and quantity adjustments
- Maintains gift status through checkout process
- Ensures quantities respect rule limitations even when manually adjusted
- Shows associated promotion rule for each gift item

**Technical details:**
- Uses extension attributes to track gift status
- Integrates with Magento's cart and checkout UI
- Handles both percentage discounts and free gift items
- Proper display of pricing information for partially discounted gifts
- Maintains gift status through checkout and order placement

### Loyalty Page with Activity Log
The module provides a comprehensive loyalty page with a navigation system and activity log component.

#### Loyalty Page Navigation System
A flexible and responsive navigation system for the loyalty page that allows customers to easily navigate between different loyalty program sections.

**Key features:**
- Sticky navigation bar that follows the user as they scroll
- Smooth scrolling to different sections
- Automatic highlighting of the active section during scroll
- SVG icon support for visual navigation elements
- Fully responsive design (adapts to all screen sizes)
- Configurable section ordering and visibility
- Support for dynamic content loading

**Implementation details:**
- Uses a modular architecture with `NavItem` blocks for each navigation item
- Navigation automatically updates when sections are added or removed
- Sections are ordered by configurable sort order values
- SVG icons are automatically mapped to navigation items
- Navigation state persists during page navigation

**Example usage in layout:**
```xml
<!-- Configure loyalty page layout -->
<referenceBlock name="leat.loyalty.index">
    <arguments>
        <argument name="section_config" xsi:type="array">
            <item name="balance" xsi:type="array">
                <item name="enabled" xsi:type="boolean">true</item>
                <item name="label" xsi:type="string">Your Balance</item>
                <item name="id" xsi:type="string">leat-balance</item>
                <item name="sort_order" xsi:type="number">10</item>
                <item name="class" xsi:type="string">Leat\LoyaltyFrontend\Block\Widget\Balance</item>
            </item>
            <item name="activity" xsi:type="array">
                <item name="enabled" xsi:type="boolean">true</item>
                <item name="label" xsi:type="string">Your Activity</item>
                <item name="id" xsi:type="string">leat-activity</item>
                <item name="sort_order" xsi:type="number">20</item>
                <item name="class" xsi:type="string">Leat\LoyaltyFrontend\Block\Widget\Activity</item>
            </item>
        </argument>
    </arguments>
</referenceBlock>
```

#### Activity Log Component
An interactive component that displays the customer's loyalty program activity history.

**Key features:**
- Real-time updates via Knockout.js
- Displays transaction history with points earned/spent
- Shows transaction dates and associated order IDs
- Proper formatting of point values with +/- indicators
- Fallback server-side rendering for initial page load
- Smooth transition from server-rendered to client-rendered data

**Implementation details:**
- Uses Magento UI Component architecture
- Subscribes to customer-data sections for real-time updates
- Format helpers for consistent display of points and dates
- Knockout templates for reactive UI updates
- Compatible with Magento's private content mechanism

**Example usage in KnockoutJS template:**
```html
<div class="leat-activity-log">
    <h3 data-bind="i18n: 'Recent Activity'"></h3>
    <!-- ko if: transactions().length -->
    <div class="transactions-list">
        <div class="transaction-item" data-bind="foreach: transactions">
            <div class="transaction-details">
                <span class="transaction-action" data-bind="text: action"></span>
                <span class="transaction-points" data-bind="text: formattedPoints"></span>
                <span class="transaction-date" data-bind="text: date"></span>
                <span class="transaction-order" data-bind="text: orderId"></span>
            </div>
        </div>
    </div>
    <!-- /ko -->
    <!-- ko if: !transactions().length -->
    <div class="empty-transactions">
        <p data-bind="i18n: 'No activity found'"></p>
    </div>
    <!-- /ko -->
</div>
```

### Prepaid Balance Slider
A checkout component that allows customers to use their prepaid loyalty balance toward their purchases.

**Key features:**
- Interactive slider to select amount of prepaid balance to apply
- Real-time total recalculation
- Manual input option for precise amounts
- Validation to prevent using more balance than available
- Mobile-friendly responsive design
- Seamless integration with Magento checkout flow
- Complete error handling and logging
- Visible in checkout summary totals
- Order summary component showing applied balance
- Persistent slider position between page reloads

**Configuration options:**
- Enable/Disable: Turn the feature on/off in admin
- Section Title: Customize the title shown in checkout

**Technical details:**
- Adds extension attributes to quote and order
- Implements totals collectors for proper discount application
- Uses Magento's checkout framework for compatibility
- Supports all payment methods for remaining balance
- Displays in frontend order totals
- Persists through checkout steps and page reloads
- Properly transfers to order during checkout completion
- Complete totals integration for orders, invoices, and creditmemos
- Order summary display with applied balance information
- Logs all balance operations for audit purposes

### Referral System
The module provides a comprehensive referral system with two main components:

#### Refer A Friend Widget
A widget that allows customers to share their referral link with friends through various social channels.

**Key features:**
- Displays a personalized referral link for logged-in customers
- Provides social sharing options (Copy, Twitter/X, WhatsApp, Email, SMS)
- Responsive design (desktop and mobile layouts)
- Fully customizable text and enabled sharing options
- Non-cacheable to ensure personalized content

**Configuration options:**
- Widget Heading: Main heading for the widget
- Section Title: Title displayed in the referral box
- Section Subtitle: Subtitle with additional information
- Share Message: The message that will be shared via social channels
- Email Subject: Subject line for email sharing
- Enabled Social Icons: Selection of which social sharing options to display

**Usage notes:**
- This widget should only be placed on non-cached pages, as it requires customer-specific data
- The widget automatically checks if the customer is logged in and has a Leat account

#### Referral Popup
A popup that appears when visitors arrive with a referral link containing the `___referral-code` URL parameter.

**Key features:**
- Automatically detects referral code in URL parameter
- Shows a stylish popup with discount information
- Collects visitor email addresses
- Processes registrations asynchronously
- Sends discount code via email
- Responsive design for all devices
- Maintains full page cacheability

**Technical implementation:**
- Uses AJAX to load popup content only when needed
- Session storage to prevent multiple popups

### Frontend Components
The module includes various frontend components for the Leat loyalty system:

- Checkout components for applying loyalty credits
- Customer account section for loyalty program

## Technical Details

### Dependencies
- Leat_Loyalty: Core loyalty functionality
- Leat_AsyncQueue: For asynchronous processing
- Leat_LoyaltyAsync: For integration with async processing
- Magento_Customer: For customer session and data
- Magento_Store: For store configuration
- Magento_Widget: For widget functionality

### JavaScript Components
The module includes the following JavaScript components:
- `leatReferCopy.js`: Handles copying referral links to clipboard
- `leatReferralPopup.js`: Manages referral popup detection and display
- `leatBalance.js`: Handles prepaid balance slider functionality in checkout
- `checkout/summary/leat-balance.js`: Displays applied balance in checkout summary
- `progress-button.js`: Provides visual feedback for asynchronous button operations
- `view/your-coupons.js`: Manages coupon redemption UI with toggle functionality
- `cart/gift-item-indicator.js`: Enhances cart display for gift items
- `checkout/gift-item-processor.js`: Maintains gift status through checkout
- `view/leat-activity-log.js`: Manages the activity log component with Knockout.js

### Controllers
- `Referral/Popup.php`: Serves popup content via AJAX
- `Referral/Subscribe.php`: Processes referral signups
- `Checkout/ApplyBalance.php`: Handles prepaid balance application
- `Balance/GetApplied.php`: Returns currently applied balance amount
- `Loyalty/Index.php`: Renders the main loyalty page

### Blocks
- `Loyalty/Index.php`: Main container block for loyalty page
- `Loyalty/Navigation.php`: Navigation component for loyalty page
- `Loyalty/NavItem.php`: Individual navigation item with icon support

### Styling
Styles are implemented using LESS and follow Magento's responsive design patterns.
Styles can be found in `web/css/source/_module.less`.

## Installation
The module is installed as part of the Leat Loyalty package. No separate installation is required.

## Configuration
Configuration for the frontend components is managed through the Leat Loyalty configuration in the Magento admin.

### Prepaid Balance Configuration
The prepaid balance slider can be configured under:
Stores > Configuration > Leat Loyalty > Leat Loyalty > Prepaid Balance

**Available settings:**
- Enable Prepaid Balance in Checkout: Enable/disable the feature
- Section Title: Change the title displayed in checkout

### Loyalty Page Configuration
The loyalty page is configured through layout XML. You can customize sections, their order, and visibility:

```xml
<referenceBlock name="leat.loyalty.index">
    <arguments>
        <argument name="section_config" xsi:type="array">
            <!-- Define sections here -->
            <item name="balance" xsi:type="array">
                <item name="enabled" xsi:type="boolean">true</item>
                <item name="label" xsi:type="string">Your Balance</item>
                <item name="id" xsi:type="string">leat-balance</item>
                <item name="sort_order" xsi:type="number">10</item>
                <item name="class" xsi:type="string">Leat\LoyaltyFrontend\Block\Widget\Balance</item>
            </item>
            <!-- Additional sections -->
        </argument>
    </arguments>
</referenceBlock>
```
