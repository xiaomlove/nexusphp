<?php

namespace App\Jobs;

use App\Models\Plugin;
use App\Repositories\PluginRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ManagePlugin implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Plugin $plugin;

    private string $action;

    public int $uniqueFor = 600;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Plugin $plugin, string $action)
    {
        $this->plugin = $plugin;
        $this->action = $action;
    }

    public function uniqueId()
    {
        return $this->plugin->id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(PluginRepository $pluginRepository)
    {
        match ($this->action) {
            'install' => $pluginRepository->doInstall($this->plugin),
            'update' => $pluginRepository->doUpdate($this->plugin),
            'delete' => $pluginRepository->doDelete($this->plugin),
            default => throw new \InvalidArgumentException("Invalid action: " . $this->action)
        };
    }
}
