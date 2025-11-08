<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the test user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = 'test@example.com';
        $password = 'password';
        
        $user = User::where('email', $email)->first();
        
        if ($user) {
            $user->password = Hash::make($password);
            $user->name = 'Test User';
            $user->save();
            $this->info('User updated successfully!');
        } else {
            User::create([
                'name' => 'Test User',
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $this->info('User created successfully!');
        }
        
        $this->info("Email: {$email}");
        $this->info("Password: {$password}");
        
        return 0;
    }
}

