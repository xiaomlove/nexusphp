<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    private static string $defaultStyleSheet = "";

    private static int $sequence = 1;


    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $password = "123456";
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        if (self::$defaultStyleSheet == "") {
            self::$defaultStyleSheet = get_setting("main.defstylesheet");
        }
        $username = sprintf("%s_%s", microtime(true), self::$sequence);
        $email = sprintf("%s@example.net", $username);
        self::$sequence++;
        $randNum = random_int(1, 10);
        if ($randNum >= 8) {
            $class = random_int(intval(User::CLASS_POWER_USER), intval(User::CLASS_SYSOP));
        } else {
            $class = User::CLASS_USER;
        }
        return [
            'username' => $username,
            'email' => $email,
            'secret' => mksecret(),
            'editsecret' => "",
            'passhash' => $passhash,
            'stylesheet' => self::$defaultStyleSheet,
            'added' => now()->toDateTimeString(),
            'status' => User::STATUS_CONFIRMED,
            'class' => $class
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
//    public function unverified()
//    {
//        return $this->state(function (array $attributes) {
//            return [
//                'email_verified_at' => null,
//            ];
//        });
//    }
}
