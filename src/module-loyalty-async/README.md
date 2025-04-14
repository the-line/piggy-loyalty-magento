# Leat_LoyaltyAsync module

This module handles asynchronous integration between Magento and the Piggy.eu loyalty software. It extends the Leat_AsyncQueue module to provide specialized queue handling for Leat-related operations.

## Overview

LeatAsync is responsible for:
- Managing all Leat API communications through an asynchronous queue system
- Providing specialized queue types for different Leat API operations
- Ensuring reliable data synchronization between Magento and Leat
- Handling retry logic, error reporting, and performance optimization
- Providing builder services for common Leat integrations
- Supporting real-time validation and testing of the Leat connection

## Async Queue Implementation

To prevent data loss, the syncing process to Leat makes use of the AsyncQueue system.  
This queue sends any jobs waiting to Leat every fifteen minutes.  
The queue ensures all waiting jobs are executed in the order they were inserted in for a given customer.  
It does this by looking ahead in the queue and checking if there are any jobs that have not successfully completed.  

The only time the execution of a job is allowed to not be in a sequential order, is the existence of a 'contact_create' 
request. Any customer that has an outstanding contact_create request, will have all jobs skipped until this request is
completed.  

Upon the failure of a request, the queue will wait for a linearly increasing amount of time for a total of seven attempts.  
The first attempt will be instant, the second attempt two hours afterwards and the seventh attempt twenty-four hours
after the sixth. This delay is built in for cases where Leat is offline or in maintenance.  

To ensure errors in the queue are caught, every error is logged in the responsible request with a mail being sent out
to the shop manager every monday at 10 am if any errors have been reported.  
There is also a cron that runs on the first of every month to clear out all successful jobs. This is to prevent an
excessive amount of unnecessary data being stored for long periods of time.

When the job digester, or any other functionality that requires a connection with leat, fails to make a connection,
an email will be sent to the shop manager to have this issue investigated.

To allow for the addition of rate limiting, the Leat Client class has been overwritten. The primary reasoning for the
introduction of rate limiting is to reduce performance impact on both parties. To ensures the entire queue of jobs isn't
sent in the smallest timeframe possible, but spread out over a longer period of time. It would take
4500 jobs, at the rate of 5 per second to exceed the timeframe for the next cron to run. This is a figure that'd be 
improbable at the best of times, the only exception being when the leat:mass:create-contact command in run.  

It is also uncertain if the current Leat API has rate limiting built in or is planning on introducing this at some point
in the future, so having it as a 'just in case' isn't necessarily a bad thing.

## Architecture

### Queue Types
All interactions with the Leat API should follow a layered architecture:

1. **Queue Types** - Core API interaction logic 
2. **Builder Services** - Orchestration and job management
3. **Controllers/Blocks/UI Components** - Frontend interfaces

### AsyncQueue Types
- Create dedicated Type classes under `Leat\LoyaltyAsync\Model\Queue\Type\` for each API operation
- Extend `ContactType` or `LeatGenericType` based on the context
- Implement the `execute()` method with the actual API call logic
- Implement a static `getTypeCode()` method that returns the type code
- Implement the `prepareData()` method to process input data
- Register types in `di.xml` under the `RequestTypePool` configuration
- Example location: `Leat\LoyaltyAsync\Model\Queue\Type\Contact\Reward\RedeemReward`

### Builder Services
- Create Builder service classes for logical groupings of API operations
- Place these in `Leat\LoyaltyAsync\Model\Queue\Builder\Service\` directory
- Inject `LeatJobBuilder`, `RequestTypePool`, and other required dependencies
- Do NOT use ObjectManager directly for obtaining services
- For read-only operations (like listing data):
  - Create temporary jobs that are not saved to the database
  - Execute these jobs immediately for synchronous responses
- For write operations (like redeeming/creating):
  - Create persistent jobs saved to the database for traceability
  - Execute these jobs immediately when synchronous responses are needed
- Example location: `Leat\LoyaltyAsync\Model\Queue\Builder\Service\RewardBuilder`

### Integration Flow
1. Define Type classes for API operations
2. Register Types in di.xml
3. Create Builder service that uses these Types
4. Inject Builder service into controllers/blocks
5. Use the Builder service to perform operations

### Prohibited Practices
- DO NOT use ObjectManager directly
- DO NOT access the Leat API client directly from controllers or blocks
- DO NOT create AsyncQueue jobs without using the Builder services
- DO NOT bypass the AsyncQueue system for API operations

## Available Queue Types

- Contact
  - ContactCreate - Creates new contacts in Leat
  - ContactUpdate - Updates existing contact information
- Credit
  - Transaction - Records credit transactions
- Order
  - OrderItemTransaction - Processes order items credit calculations

## Service Layer

### ConnectionTester Service
- Provides real-time connection testing to the Leat API
- Validates API credentials (Personal Access Token and Shop UUID)
- Returns detailed connection status with error messages when applicable
- Supports multi-store configurations with store-specific credential testing
- Utilized by the admin UI for connection status display
- Returns connection information including company name and ID when successful

### Builder Services

- ContactBuilder - Handles contact creation and updates
- OrderBuilder - Manages order synchronization
- RewardBuilder - Handles reward operations (redeeming, crediting)

## Cron Jobs

The module provides several scheduled tasks:
- OrderExport - Exports new orders to Leat
- ContactUpdate - Updates contact information
- CreditFalloff - Manages credit expiration logic

Following these guidelines ensures consistent implementation, proper error handling, and traceable operations throughout the Leat integration.
