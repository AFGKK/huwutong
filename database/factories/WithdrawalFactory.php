<?php

namespace Database\Factories;

use App\Models\EarningsAccount;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    public function definition(): array
    {
        $channel = $this->faker->randomElement(['bank', 'alipay', 'wechat', 'paypal']);
        $amount = $this->faker->randomFloat(2, 100, 50000);
        $fee = round($amount * match($channel) {
            'bank' => 0.01,
            'alipay', 'wechat' => 0.006,
            'paypal' => 0.044,
        }, 2);

        $data = [
            'earnings_account_id' => EarningsAccount::factory(),
            'user_id' => fn(array $attrs) => EarningsAccount::find($attrs['earnings_account_id'])?->user_id ?? User::factory(),
            'amount' => $amount,
            'fee' => $fee,
            'net_amount' => $amount - $fee,
            'channel' => $channel,
            'status' => $this->faker->randomElement(['pending_review', 'pending', 'completed']),
        ];

        // Fill channel-specific fields
        switch ($channel) {
            case 'bank':
                $data['bank_name'] = $this->faker->company;
                $data['bank_branch'] = $this->faker->city . '支行';
                $data['bank_account_name'] = $this->faker->name;
                $data['bank_account_no'] = $this->faker->bankAccountNumber;
                $data['channel_account'] = $data['bank_account_no'];
                break;
            case 'alipay':
                $data['alipay_account'] = $this->faker->email;
                $data['channel_account'] = $data['alipay_account'];
                break;
            case 'wechat':
                $data['wechat_account'] = $this->faker->userName;
                $data['channel_account'] = $data['wechat_account'];
                break;
            case 'paypal':
                $data['paypal_email'] = $this->faker->email;
                $data['channel_account'] = $data['paypal_email'];
                break;
        }

        return $data;
    }

    public function pendingReview(): static
    {
        return $this->state(fn() => ['status' => 'pending_review']);
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => 'completed',
            'transaction_id' => 'TXN' . $this->faker->uuid,
            'completed_at' => now(),
        ]);
    }

    public function withChannel(string $channel): static
    {
        return $this->state(fn() => ['channel' => $channel]);
    }
}
