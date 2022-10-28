<?php
namespace App\Repositories;

use App\Models\Plugin;

class PluginRepository extends BaseRepository
{
    public function cronjob($action = null, $id = null, $force = false)
    {
        if ($action == 'install' || $action === null) {
            $this->doCronjob('install', $id, $force, Plugin::STATUS_PRE_INSTALL, Plugin::STATUS_INSTALLING);
        }
        if ($action == 'delete' || $action === null) {
            $this->doCronjob('delete', $id, $force, Plugin::STATUS_PRE_DELETE, Plugin::STATUS_DELETING);
        }
        if ($action == 'update' || $action === null) {
            $this->doCronjob('update', $id, $force, Plugin::STATUS_PRE_UPDATE, Plugin::STATUS_UPDATING);
        }
    }

    private function doCronjob($action, $id, $force, $preStatus, $doingStatus)
    {
        $query = Plugin::query();
        if (!$force) {
            $query->where('status', $preStatus);
        }
        if ($id !== null) {
            $query->where("id", $id);
        }
        $list = $query->get();
        if ($list->isEmpty()) {
            do_log("No plugin need to be $action...");
            return;
        }
        $idArr = $list->pluck('id')->toArray();
        Plugin::query()->whereIn('id', $idArr)->update(['status' => $doingStatus]);
        foreach ($list as $item) {
            match ($action) {
                'install' => $this->doInstall($item),
                'update' => $this->doUpdate($item),
                'delete' => $this->doDelete($item),
                default => throw new \InvalidArgumentException("Invalid action: $action")
            };
        }
    }

    public function doInstall(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_INSTALLING]);
        $packageName = $plugin->package_name;
        try {
            $this->execComposerConfig($plugin);
            $this->execComposerRequire($plugin);
            $output = $this->execPluginInstall($plugin);
            $version = $this->getInstalledVersion($packageName);
            do_log("success install plugin: $packageName version: $version");
            $update = [
                'status' => Plugin::STATUS_NORMAL,
                'status_result' => $output,
                'installed_version' => $version
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_INSTALL_FAILED,
                'status_result' => $throwable->getMessage()
            ];
            do_log("fail install plugin: " . $packageName);
        } finally {
            $this->updateResult($plugin, $update);
        }

    }

    public function doDelete(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_DELETING]);
        $packageName = $plugin->package_name;
        $removeSuccess = true;
        try {
            $output = $this->execComposerRemove($plugin);
            do_log("success remove plugin: $packageName");
            $update = [
                'status' => Plugin::STATUS_NOT_INSTALLED,
                'status_result' => $output,
                'installed_version' => null,
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_DELETE_FAILED,
                'status_result' => $throwable->getMessage()
            ];
            $removeSuccess = false;
            do_log("fail remove plugin: " . $packageName);
        } finally {
            if ($removeSuccess) {
                $plugin->delete();
            } else {
                $this->updateResult($plugin, $update);
            }
        }

    }

    public function doUpdate(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_UPDATING]);
        $packageName = $plugin->package_name;
        try {
            $output = $this->execComposerUpdate($plugin);
            $this->execPluginInstall($plugin);
            $version = $this->getInstalledVersion($packageName);
            do_log("success update plugin: $packageName to version: $version");
            $update = [
                'status' => Plugin::STATUS_NORMAL,
                'status_result' => $output,
                'installed_version' => $version,
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_UPDATE_FAILED,
                'status_result' => $throwable->getMessage()
            ];
            do_log("fail update plugin: " . $packageName);
        } finally {
            $this->updateResult($plugin, $update);
        }

    }

    private function getRepositoryKey(Plugin $plugin)
    {
        return str_replace("xiaomlove/nexusphp-", "", $plugin->package_name);
    }

    private function execComposerConfig(Plugin $plugin)
    {
        $command = sprintf("composer config repositories.%s git %s", $this->getRepositoryKey($plugin), $plugin->remote_url);
        do_log("[COMPOSER_CONFIG]: $command");
        return $this->executeCommand($command);
    }

    private function execComposerRequire(Plugin $plugin)
    {
        $command = sprintf("composer require %s", $plugin->package_name);
        do_log("[COMPOSER_REQUIRE]: $command");
        return $this->executeCommand($command);
    }

    private function execComposerRemove(Plugin $plugin)
    {
        $command = sprintf("composer remove %s", $plugin->package_name);
        do_log("[COMPOSER_REMOVE]: $command");
        return $this->executeCommand($command);
    }

    private function execComposerUpdate(Plugin $plugin)
    {
        $command = sprintf("composer update %s", $plugin->package_name);
        do_log("[COMPOSER_UPDATE]: $command");
        return $this->executeCommand($command);
    }

    private function execPluginInstall(Plugin $plugin)
    {
        $command = sprintf("php artisan plugin install %s", $plugin->package_name);
        do_log("[PLUGIN_INSTALL]: $command");
        return $this->executeCommand($command);
    }

    private function updateResult(Plugin $plugin, array $update)
    {
        $update['status_result'] = $update['status_result'] . "\n\nREQUEST_ID: " . nexus()->getRequestId();
        do_log("[UPDATE]: " . json_encode($update));
        $plugin->update($update);
    }

    public function getInstalledVersion($packageName)
    {
        $command = sprintf('composer info |grep -E %s', $packageName);
        $result = $this->executeCommand($command);
        $parts = preg_split("/[\s]+/", trim($result));
        $version = $parts[1];
        if (str_contains($version, 'dev')) {
            $version .= " $parts[2]";
        }
        return $version;
    }


}
