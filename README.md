# Leat Loyalty for Magento 2

## Overview

Leat Loyalty for Magento 2 is a comprehensive integration package that connects your Magento 2 store with the Piggy.eu loyalty platform. This integration enables powerful loyalty features including points earning, rewards redemption, prepaid balance, gift products, and referral systems.

The package consists of five modules that work together to provide a complete loyalty solution:

- **Leat_Loyalty**: Core functionality for Leat loyalty integration
- **Leat_LoyaltyFrontend**: Frontend components and widgets
- **Leat_LoyaltyAdminUI**: Admin interfaces and backend functionality
- **Leat_LoyaltyAsync**: Asynchronous integration with Piggy.eu
- **Leat_AsyncQueue**: Robust asynchronous job processing system

## Requirements

- PHP 8.3 or higher
- Magento 2.4.7+ (framework >=103.0.7) with the Magento repository (repo.magento.com) configured
- [Piggy PHP SDK](https://github.com/Piggy-Loyalty/piggy-php-sdk) ^3.12
- Composer

## Installation

### Via Composer (Recommended)

1. Require the package:

```bash
composer require leat/magento2-loyalty
```

2. Enable the modules:

```bash
bin/magento module:enable Leat_Loyalty Leat_LoyaltyFrontend Leat_LoyaltyAdminUI Leat_LoyaltyAsync Leat_AsyncQueue
```

3. Run Magento setup upgrade:

```bash
bin/magento setup:upgrade
```

4. Compile Magento (production mode):

```bash
bin/magento setup:di:compile
```

5. Deploy static content (production mode):

```bash
bin/magento setup:static-content:deploy
```

6. Clear the cache:

```bash
bin/magento cache:clean
bin/magento cache:flush
```

## Configuration

### API Credentials

1. Navigate to **Stores > Configuration > Leat Loyalty > Leat Loyalty > Connection**
2. Enter your Leat API credentials:
   - **Personal Access Token**: Your Leat API key
   - **Shop UUID**: Your Leat shop identifier
3. Test the connection using the "Test Connection" button
4. Save the configuration

### Store Configuration

1. Navigate to **Stores > Configuration > Leat Loyalty > Leat Loyalty > General Configuration**
2. Enable Leat Connection
3. Select which customer groups should be integrated with Leat
4. Save the configuration

### Additional Settings

- **Credits Display**: Configure under **Stores > Configuration > Leat Loyalty > Leat Loyalty > Credits Display**
- **Order Configuration**: Configure under **Stores > Configuration > Leat Loyalty > Leat Loyalty > Order Configuration**
- **Prepaid Balance**: Configure under **Stores > Configuration > Leat Loyalty > Leat Loyalty > Prepaid Balance**
- **Refer a Friend**: Configure under **Stores > Configuration > Leat Loyalty > Leat Loyalty > Refer a Friend**

## Module Descriptions

### Leat_Loyalty

The core module that provides the foundation for the Leat loyalty integration:

- Customer synchronization with Leat contacts
- Order synchronization and credit calculation
- Prepaid balance management
- Coupon/rewards redemption
- Gift product cart price rules

[Detailed Documentation](src/module-loyalty/README.md)

### Leat_LoyaltyFrontend

Provides frontend components and widgets for customer interaction:

- Loyalty page with activity log
- Prepaid balance slider in checkout
- Referral system with sharing options
- Gift products display in cart
- Your Coupons widget

[Detailed Documentation](src/module-loyalty-frontend/README.md)

### Leat_LoyaltyAdminUI

Extends Magento's admin area with Leat-specific features:

- Data synchronization interface
- Connection testing system
- Gift product cart price rule UI
- Leat coupon type integration
- Validation framework for data integrity

[Detailed Documentation](src/module-loyalty-admin-ui/README.md)

### Leat_LoyaltyAsync

Handles asynchronous communication between Magento and Leat:

- Specialized queue types for Leat API operations
- Builder services for common integrations
- Retry logic and error reporting
- Performance optimization

[Detailed Documentation](src/module-loyalty-async/README.md)

### Leat_AsyncQueue

Provides a robust asynchronous job processing system:

- Job and request management
- Sequential processing
- Retry handling with progressive delays
- Performance optimization

[Detailed Documentation](src/module-async-queue/README.md)

## Key Features

### Customer Synchronization

The integration automatically synchronizes customer data with Leat:

- Creates Leat contacts for Magento customers
- Syncs customer profile updates (name, email, address)
- Stores contact UUID for reliable identification

### Order Synchronization

Orders are automatically synchronized to Leat:

- Transactions created for each order item

### Prepaid Balance

Allows customers to use their loyalty balance as payment:

- Interactive slider in checkout
- Real-time total recalculation
- Validation to prevent overuse
- Complete integration with Magento checkout

### Gift Product Promotions

Enhanced cart price rules for gift products:

- Add gift products to cart based on rules
- Support for both simple and configurable products
- Percentage-based discounts
- Quantity limits based on rule settings

### Referral System

Complete referral functionality:

- Personalized referral links
- Social sharing options
- Referral popup for new visitors
- Email notifications

### Frontend Widgets

Rich set of frontend components:

- Loyalty widget with authentication
- Activity log showing transaction history
- Your Coupons widget for reward redemption
- Progress button component for visual feedback

## Cron Jobs

The integration sets up several cron jobs:

- **Order Export**: Exports new orders to Leat (hourly)
- **Contact Update**: Updates contact information (daily)
- **Queue Processing**: Processes the async queue (every 15 minutes)
- **Queue Alert**: Sends alerts for queue errors (Monday at 10 AM)
- **Queue Cleanup**: Cleans up successful jobs (monthly)

## Troubleshooting

### Common Issues

#### Connection Issues

If you experience connection issues with the Leat API:

1. Verify your API credentials in the configuration
2. Test the connection using the "Test Connection" button
3. Check your server's outbound connectivity to api.piggy.eu
4. Check the log files in the `var/log/leat/` directory for specific error messages

#### Queue Processing Issues

If jobs are not being processed:

1. Ensure cron is properly configured and running
2. Check the log files in the `var/log/leat/` directory for errors
3. Verify the queue tables in the database are not corrupted
4. Run `bin/magento cron:run --group=leat_async_queue` and `bin/magento cron:run --group=leat_integration` manually to test

#### Synchronization Issues

If customer or order data is not syncing:

1. Check if the stores are properly configured in the Leat settings
2. Verify the customer has a contact UUID assigned
3. Check the log files in the `var/log/leat/` directory for synchronization errors

### Logging

The integration writes log files to the `var/log/leat/` directory. These logs contain detailed information about:

- Loyalty operations
- API communications
- Queue processing
- Error messages and exceptions

You can check these logs for troubleshooting and monitoring the integration's activities.

## Support and Resources

- **GitHub Repository**: [Piggy-Loyalty/magento](hhttps://github.com/Piggy-Loyalty/magento)
- **Technical Support**: techniek@piggy.nl
- **Documentation**: Refer to individual module README files for detailed documentation

## License

MIT License - see composer.json for details.
