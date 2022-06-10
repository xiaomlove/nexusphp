<?php

namespace Nexus\Plugin;

class Hook
{
    private static array $callbacks = [];

    public function addFilter($name, $function, $priority, $argc)
    {
        $id = $this->buildUniqueId($function);
        self::$callbacks[$name][$priority][$id] = ['function' => $function, 'argc' => $argc];
    }

    private function buildUniqueId($function): string
    {
        if (is_string($function)) {
            return $function;
        } elseif (is_object($function) && ($function instanceof \Closure)) {
            //Closure
            return spl_object_hash($function);
        } elseif (is_array($function)) {
            if (is_object($function[0])) {
                return spl_object_hash($function[0]).$function[1];
            } elseif (is_string($function[0])) {
                return $function[0].'::'.$function[1];
            }
        }
        throw new \InvalidArgumentException("Invalid function, type: " . gettype($function));
    }

    public function applyFilter($name, $value = '')
    {
        if (!isset(self::$callbacks[$name])) {
            do_log("No this hook: $name");
            return $value;
        }
        $args = func_get_args();
        ksort(self::$callbacks[$name]);
        reset(self::$callbacks[$name]);
        do_log("name: $name, argc: " . (func_num_args() - 1));
        do {
            foreach ((array)current(self::$callbacks[$name]) as $id => $callback) {
                $args[1] = $value;
//                do_log("name: $name, id: $id, before, params: " . nexus_json_encode(array_slice($args, 1, $callback['argc'])));
                $value = call_user_func_array($callback['function'], array_slice($args, 1, $callback['argc']));
//                do_log("name: $name, id: $id, after, value: " . nexus_json_encode($value));
            }
        }
        while (next(self::$callbacks[$name]) !== false);
        return $value;
    }

    public function addAction($name, $function, $priority, $argc)
    {
        return $this->addFilter($name, $function, $priority, $argc);
    }

    public function doAction($name, $value = '')
    {
        if (!isset(self::$callbacks[$name])) {
            do_log("No this hook: $name");
            return;
        }
        $args = func_get_args();
        ksort(self::$callbacks[$name]);
        reset(self::$callbacks[$name]);
        do_log("name: $name, argc: " . (func_num_args() - 1));
        do {
            foreach ((array)current(self::$callbacks[$name]) as $id => $callback) {
//                do_log("name: $name, id: $id, before, params: " . nexus_json_encode(array_slice($args, 1, $callback['argc'])));
                call_user_func_array($callback['function'], array_slice($args, 1, $callback['argc']));
            }
        }
        while (next(self::$callbacks[$name]) !== false);
    }

    public function dump()
    {
        echo '<pre>';
        var_dump(self::$callbacks);
        echo '</pre>';
    }
}
