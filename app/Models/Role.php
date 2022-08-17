<?php

namespace App\Models;

class Role extends NexusModel
{
    public $timestamps = true;

    protected $fillable = ['name', 'class'];

    public function permissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Permission::class, 'role_id');
    }

    public function getClassTextAttribute()
    {
        if ($this->class < 0) {
            return '';
        }
        return User::getClassText($this->class);
    }

    public static function initClassRoles()
    {
        foreach (User::$classes as $class => $info) {
            $attributes = [
                'class' => $class
            ];
            $values = [
                'name' => $info['text'],
            ];
            Role::query()->firstOrCreate($attributes, $values);
        }
    }

}
