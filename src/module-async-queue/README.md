# AsyncQueue Module for Magento 2

## Overview

The AsyncQueue module provides a robust asynchronous job processing system for Magento 2. It enables reliable execution of operations that benefit from background processing, such as API calls to external services, lengthy data processing tasks, or multi-step operations that should be executed sequentially.

## Key Concepts

### Jobs and Requests

The module operates on two primary entities:

- **Job**: A container for multiple related requests that should be executed sequentially
- **Request**: An individual operation with a specific type and payload data

### Queue Process

1. **Request Creation**: Requests are created and added to a job
2. **Job Queuing**: Jobs are persisted to the database for asynchronous processing
3. **Scheduled Processing**: The JobDigest service processes jobs through a cron job
4. **Sequential Execution**: Requests within a job are processed in the order they were added
5. **Retry Handling**: Failed requests are retried with progressive delays

## Async vs Direct Execution

The module supports two execution models:

- **Asynchronous**: Jobs are processed by cron tasks (default behavior)
- **Direct**: Requests can be executed immediately, bypassing the queue

## Usage Examples

### Creating an Asynchronous Job

```php
use Leat\AsyncQueue\Model\Builder\JobBuilderInterface;
use Leat\AsyncQueue\Api\JobRepositoryInterface;

class MyService
{
    public function __construct(
        private JobBuilderInterface $jobBuilder,
        private JobRepositoryInterface $jobRepository
    ) {}

    public function createAsyncJob(string $customerId, int $storeId): void
    {
        // Create a new job
        $job = $this->jobBuilder
            ->setRelationId($customerId)
            ->setStoreId($storeId)
            ->create();

        // Add requests to the job
        $job->getRequestBuilder()
            ->setTypeCode('customer_create')
            ->setPayload(['customer_id' => $customerId, 'action' => 'create'])
            ->create();

        $job->getRequestBuilder()
            ->setTypeCode('customer_notify')
            ->setPayload(['customer_id' => $customerId, 'template' => 'welcome'])
            ->create();

        // Save the job to be processed asynchronously
        $this->jobRepository->save($job);
    }
}
```

### Direct Execution (Bypass Queue)

You can execute a request type directly without using the queue by obtaining the type from the RequestTypePool and calling its methods directly:

```php
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\AsyncQueue\Model\Queue\Request\TypeInterface;

class MyDirectService
{
    public function __construct(
        private RequestTypePool $requestTypePool
    ) {}

    public function executeDirectly(string $customerId, array $data): void
    {
        try {
            // Get the request type from the pool
            /** @var TypeInterface $type */
            $type = $this->requestTypePool->getType('customer_create');
            
            // Manually prepare the payload
            $payload = json_encode([
                'customer_id' => $customerId,
                'data' => $data
            ]);
            
            // Unpack the payload directly
            $type->unpack($payload);
            
            // Execute immediately
            // Note: Since we're not using a job/request, we'd need to create mock objects
            // or modify the type to support direct execution
            
            // Alternative if the type requires a Job and Request:
            // 1. Create a temporary job and request
            // 2. Call beforeExecute with these objects
            // 3. Don't save the job/request to the database
            
            $logger = $type->getConnector()->getLogger();
            $logger->info('Direct execution completed for customer ' . $customerId);
        } catch (\Throwable $exception) {
            // Handle exceptions directly
            $this->logAndNotify($exception);
        }
    }
}
```

### Using JobDigest for Immediate Execution

For more complex scenarios where you need the full job/request functionality but want immediate execution:

```php
use Leat\AsyncQueue\Model\Builder\JobBuilderInterface;
use Leat\AsyncQueue\Service\JobDigest;

class MyImmediateService
{
    public function __construct(
        private JobBuilderInterface $jobBuilder,
        private JobDigest $jobDigest
    ) {}

    public function executeImmediately(string $customerId, int $storeId): void
    {
        // Create a job with requests
        $job = $this->jobBuilder
            ->setRelationId($customerId)
            ->setStoreId($storeId)
            ->create();

        $job->getRequestBuilder()
            ->setTypeCode('customer_create')
            ->setPayload(['customer_id' => $customerId])
            ->create();

        // Execute the job immediately
        $this->jobDigest
            ->setJob($job)  // Set a specific job to process
            ->execute();    // Process immediately
    }
}
```

## Request Type Implementation

Each request type must implement the `TypeInterface`:

```php
namespace MyCompany\MyModule\Model\AsyncQueue\Request;

use Leat\AsyncQueue\Model\Queue\Request\TypeInterface;
use Leat\AsyncQueue\Model\Connector\ConnectorInterface;
use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Api\Data\RequestInterface;

class CustomerCreate implements TypeInterface
{
    private array $data = [];
    
    public function __construct(
        private ConnectorInterface $connector
    ) {}

    public function getConnector(): ConnectorInterface
    {
        return $this->connector;
    }

    public function unpack(string $payload): void
    {
        $this->data = json_decode($payload, true);
    }

    public function beforeExecute(JobInterface $job, RequestInterface $request): void
    {
        // Implement the business logic
        $customerId = $this->data['customer_id'];
        
        // Example API call
        $result = $this->connector->callApi('createCustomer', $this->data);
        
        // Log completion
        $this->connector->getLogger()->info(
            'Customer created in external system',
            ['customer_id' => $customerId, 'result' => $result]
        );
    }
}
```

## Queue Management Details

### Job Processing Flow

1. **Selection**: The cron job selects incomplete jobs (limited to prevent memory issues)
2. **Validation**: Each job is validated (e.g., checking if parent jobs are completed)
3. **Sequential Processing**: Requests are processed in sequence; a failure pauses the job
4. **Retry Logic**: Failed requests follow a progressive delay pattern:
   - 1st attempt: Immediate retry
   - 2nd attempt: Retry after 2 hours
   - 3rd attempt: Retry after 4 hours
   - And so on, until reaching maximum attempts
5. **Completion**: Once all requests in a job are processed, the job is marked complete

### Failure Handling

- If a request fails, the entire job is paused until the next processing cycle
- Failed requests track the failure reason and attempt count
- After maximum attempts, the request is considered permanently failed
- Email alerts can notify administrators of persistent failures

### Performance Considerations

- The queue processes a configurable number of jobs per execution
- Jobs are prioritized by creation time
- Database-level locking prevents duplicate processing
- Heavy processing should be designed to be resumable between job executions

## Technical Implementation

The core processing is handled by `JobDigest::execute()`, which:

1. Retrieves pending jobs from the database
2. Validates each job's readiness for processing
3. Processes requests within the job sequentially
4. Updates request and job status in the database
5. Handles exceptions and retry scheduling

## Configuration

The module can be configured through:
- Cron schedules in `crontab.xml`
- Maximum execution parameters in `JobDigest.php`
- Email templates for error notification
