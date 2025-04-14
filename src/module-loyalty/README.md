# Leat_Loyalty module

This module allows for the integration of the Piggy.eu loyalty software into Magento.
It does this by:
- Selectively synchronizing data between Magento and Leat in a way no data gets lost or inserted before it is time to do so.
- Retrieving Leat Member level from Leat and saving it in Magento.
- Managing customer loyalty credits and prepaid balance.
- Providing real-time validation for required attributes and configurations.

## Asynchronous Integration

Asynchronous communication with Piggy.eu is handled by the Leat_LoyaltyAsync module. Please refer to that module's README for details on:
- Async queue implementation
- Queue types and builders
- API interaction architecture
- Retry mechanisms and error handling

## Data Synchronization

### Attribute Synchronization
The module provides a system to ensure that all required attributes for transactions and rewards are properly defined in Leat. This is critical for the proper functioning of the integration as attributes form the foundation of data stored in Leat.

#### Required Attributes
- Transaction attributes: Predefined set of attributes required for order transactions
- Reward attributes: Attributes used for reward tracking and management

#### Synchronization Process
- The synchronization is initiated from the Magento admin panel
- The system checks existing attributes in Leat and creates any missing ones
- Validation occurs in real-time to ensure all required attributes exist
- Attributes are created with appropriate data types and settings to match Magento's data structure

#### AttributeResource
The `AttributeResource` class is responsible for:
- Validating that required attributes exist in Leat
- Creating missing attributes during synchronization
- Providing detailed validation results to the admin UI
- Supporting multi-store configurations

### Customer Sync
The customer sync creates so called 'contacts' in Leat for any account made in the shops configured in the admin.
This process will create a 'contact' for the customer based on their email-address. To allow customers to change their
email if necessary, the contact UUID is saved within their customer account. This is to prevent a mismatch between
the email address used within Magento and Leat as the email field within Leat is immutable.  

### Data
The customer sync currently ensures the following data is synced with Leat upon change:
- Email
- Firstname
- Lastname
- Address information
- TODO: DOB

### When
A job will be inserted into the queue whenever the customer changes their account information, address or when a new
account is created.  
It does this based on the following events:
- customer_register_success - New account creation !! **When enabled in configuration** !!
- customer_account_edited - Account details edited
- customer_address_save_commit_after - Address details edited


## Order Sync  
For each item in an order, a transaction will be sent to Leat containing the following fields per item:
- Increment id  
- SKU  
- Product name  
- Brand  
- Row total (product price * qty) (excl. tax/shipping)  
- Credits based on a credit distributing algorithm that ensures no points are lost.  

### When  
- An hourly cron that collects any order for any orders placed within the configured stores. These orders do follow
  The VoorKappers standard for a valid order before being exported. Where orders with paid with adyen are not exported
  if the status is not atleast processing.  

### Customer condition 
It might happen a customer from one of the configured stores has remained dormant for a long time. If this is the case,
they might not have a Leat contact assigned to them. In this case, a create request will be sent for this customer 
before the order items are inserted.

## Widget:
It is added by using the following block on the desired page/block:

`{{block class="Leat\Loyalty\Block\LoyaltyWidget" widget_id="<ID>" cacheable="false"}}`  

Where 'widget_id' should be adjusted to the id of the desired widget design. 

**Important:**
The '1 column (Disabled FPC)' page type must be used.

A block is used, instead of the given script url, to allow for more configurability.  
In this case, the widget will only show if the customer is on the correct website and has a contact_uuid assigned.  
A fetch request to a controller is used to get an authentication token for the customer contact. This will skip
the customer having to log in themselves.

The optimal position and status of the widget icon is as follows:
* Position: Right
* Open widget by default: Closed
* Offset horizontal: 48
* Offset Vertical: 100

This puts it centred and above the Robin button.

It is also vitally important that the 'login' and 'register' pages of the widget are disabled. As this logic will be
handled by Magento.

The widget opens automatically as soon as the user is logged in to the widget by the Javascript, which is busy in the background.

## Prepaid Balance

The Leat module supports applying prepaid loyalty balance to customer orders during checkout. This feature allows 
customers to use their accumulated loyalty credits as a form of payment.

### How It Works
1. The system retrieves the customer's available prepaid balance
2. During checkout, customers can choose how much of their balance to apply
3. The applied balance is validated to ensure it doesn't exceed available balance or order total
4. The balance amount is applied as a discount to the order
5. Applied balance amount is stored with the order and quote for reference

### Key Components
- **ApplyBalanceInterface** - Service contract for applying balance to a quote
- **BalanceValidatorInterface** - Validates balance amount before application
- **OrderLeatBalanceRepositoryInterface** - Manages order balance data persistence
- **Balance Total Collector** - Applies the balance as a discount in the quote totals calculation
- **Order Observer** - Transfers balance information from quote to order during order placement

### Configuration
The prepaid balance feature can be configured in the Magento admin under:
Stores > Configuration > Leat Loyalty > Leat Loyalty > Prepaid Balance

Available settings include:
- Enable/disable the feature
- Maximum percentage of order total that can be covered by prepaid balance

## Coupon/Rewards Redemption

The module allows customers to apply rewards as coupons to their shopping cart. This feature integrates the Leat loyalty rewards with Magento's native coupon functionality.

### Key Components

#### AppliedCouponsManager
- Manages the application of Leat reward coupons to quotes
- Enables customers to apply reward coupons from their account to the shopping cart
- Supports multi-coupon functionality with reward-type organization
- Handles proper storage and retrieval of applied coupons in the quote
- Manages coupon redemption lifecycle including collection at checkout

#### LoyaltyTransactionRelatedGiftRemover
- Handles proper removal of gift products when a coupon is removed
- Ensures proper cleanup of related gift items in the cart
- Maintains proper rule associations in the quote object

### Technical Implementation
- Uses JSON serialization for storing coupon data in the quote
- Integrates with Magento's native sales rule and coupon systems
- Dispatches events for other modules to hook into the coupon workflow
- Provides proper error handling and logging

## Gift Product Cart Price Rule

The module adds a new cart price rule action type that allows automatically adding gift products to the cart when a promotion rule is applied.

### Key Components

#### GiftProductManager
- Core service for managing gift products in the cart
- Adds products based on SKUs specified in the rule configuration
- Supports both simple and configurable products
- Handles proper gift marking and pricing
- Enforces gift quantity limits based on the rule's discount_qty setting
- Applies percentage discounts based on rule's discount_amount instead of making items free

#### ConfigurableProductResolver
- Resolves configurable product options when a simple product SKU is provided
- Automatically determines the parent configurable product
- Finds the appropriate super attributes to select the desired variant

#### Action/AddGiftProducts
- Implements the cart price rule action for adding gift products
- Interfaces with Magento's sales rule calculation system
- Ensures gifts are only added once per rule application
- Respects discount_qty for limiting the number of gift items
- Applies discount_amount for percentage-based discounts

#### Extension Attributes for Tracking Gift Products
- Extends CartItemInterface with extension attributes:
  - `is_gift`: Flags an item as a gift product
  - `gift_rule_id`: Links the gift item to its originating sales rule
  - `original_product_sku`: Stores the original product SKU for tracking

#### Observer/RemoveInvalidGiftProductsFromQuote
- Removes gift products when their associated rules no longer apply
- Keeps the cart in sync with eligible promotions
- Maintains consistency when cart contents change

#### EnforceGiftItemQuantitiesPlugin
- Ensures gift item quantities don't exceed the rule's discount_qty setting
- Prevents manual quantity adjustments from bypassing rule limits
- Automatically adjusts quantities when total exceeds the rule limit
- Preserves the oldest gift items when adjustments are necessary

#### LoyaltyTransactionRelatedGiftRemover
- Manages removal of gift products when associated rules are invalidated
- Handles proper cleanup of related items in the cart
- Maintains correct rule associations in the quote object

### Technical Implementation
- Fully integrates with Magento's sales rule system
- Supports comma-separated list of SKUs in rule configuration
- Uses extension attributes for persistent gift item tracking
- Handles both simple products and configurable product variants
- Applies percentage-based discounts according to rule settings
- Enforces quantity limits per rule using discount_qty
- Includes detailed logging for troubleshooting
- Prevents duplicate gift additions on cart updates
