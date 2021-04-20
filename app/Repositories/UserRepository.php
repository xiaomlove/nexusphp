<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserRepository
{
    public function store(array $params)
    {
        $required = ['username', 'email', 'password', 'password_confirmation'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \InvalidArgumentException("Require $field");
            }
        }
        $username = $params['username'];
        $email = $params['email'];
        $password = $params['password'];
        $confirmPassword = $params['password_confirmation'];

        if (!validusername($username)) {
            throw new \InvalidArgumentException("Invalid username: $username");
        }
        $email = htmlspecialchars(trim($email));
        $email = safe_email($email);
        if (!check_email($email)) {
            throw new \InvalidArgumentException("Invalid email: $email");
        }
        $exists = User::query()->where('email', $email)->exists();
        if ($exists) {
            throw new \InvalidArgumentException("The email address: $email is already in use");
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 40) {
            throw new \InvalidArgumentException("Invalid password: $password, it should be more than 6 character and less than 40 character");
        }
        if ($password != $confirmPassword) {
            throw new \InvalidArgumentException("confirmPassword: $confirmPassword != password: $password");
        }
        $setting = get_setting('main');
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        $insert = [
            'username' => $username,
            'passhash' => $passhash,
            'secret' => $secret,
            'email' => $email,
            'stylesheet' => $setting['defstylesheet'],
            'status' => 'confirmed',
            'added' => now()->toDateTimeString(),
        ];
        Log::info("create user: " . nexus_json_encode($insert));

        return User::query()->create($insert);
    }

    public function getList(array $params)
    {
        $query = User::query();
        $sortField = 'id';
        $validSortFields = ['uploaded', 'downloaded', ];
        if (!empty($params['sort']) && in_array($params['sort'], $validSortFields)) {
            $sortField = $params['sort'];
        }
        $fields = ['id', 'username', 'avatar', 'email', 'uploaded', 'downloaded', 'class', 'added'];
        if (!empty($params['fields'])) {
            $fields = $params['fields'];
        }

        if (!empty($params['id'])) {
            $query->where('id', $params['id']);
        }
        if (!empty($params['username'])) {
            $query->where('username', $params['username']);
        }
        if (!empty($params['email'])) {
            $query->where('email', $params['email']);
        }

        $result = $query->orderBy($sortField, 'desc')->select($fields)->paginate();

        return $result;

    }
}