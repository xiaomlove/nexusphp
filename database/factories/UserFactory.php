<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    private static string $defaultStyleSheet = "";


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
        return [
            'username' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'secret' => mksecret(),
            'editsecret' => "",
            'passhash' => $passhash,
            'stylesheet' => self::$defaultStyleSheet,
            'added' => now()->toDateTimeString(),
            'status' => User::STATUS_CONFIRMED,
            'class' => random_int(intval(User::CLASS_USER), intval(User::CLASS_SYSOP))
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
