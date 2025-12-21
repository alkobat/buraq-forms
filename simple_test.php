// Simple test to verify Logger works with Auth.php
require_once 'src/Core/Logger.php';
use BuraqForms\Core\Logger;

// Test the exact call used in Auth.php line 309
Logger::error('Login error occurred', [
    'email' => 'test@example.com',
    'error' => 'Test error message',
    'ip' => '127.0.0.1'
]);

echo "Logger::error() test completed successfully!\n";