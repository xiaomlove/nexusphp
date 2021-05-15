<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PeerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $seconds = $this->started->diff($this->last_action)->s;
        if ($this->uploaded == 0) {
            $uploadSpeed = mksize(0) . '/s';
        } else {
            $uploadSpeed = mksize(($this->uploaded - $this->uploadoffset) / $seconds) . '/s';
        }
        $nowTimestamp = time();
        return [
            'id' => $this->id,
            'connectable_text' => $this->connectableText,

            'upload_text' => sprintf('%s@%s', mksize($this->uploaded), $uploadSpeed),

            'download_text' => sprintf('%s@%s', mksize($this->downloaded), $this->getUploadSpeed($this->resource)),

            'share_ratio' => $this->getShareRatio($this->resource),
            'download_progress' => $this->getDownloadProgress($this->resource),
            'connect_time_total' => mkprettytime($nowTimestamp - $this->started->timestamp),
            'last_action_human' => mkprettytime($nowTimestamp - $this->last_action->timestamp),
            'agent_human' => htmlspecialchars(get_agent($this->peer_id, $this->agent)),
            'user' => new UserResource($this->whenLoaded('user')),

        ];
    }


    /**
     * 获得上传速度
     *
     * @see viewpeerlist.php
     *
     * @param $peer
     * @return string
     */
    protected function getUploadSpeed($peer)
    {
        $diff = $peer->downloaded - $peer->downloadoffset;
        if ($peer->isSeeder()) {
            $seconds = $peer->finishedat - $peer->started->getTimestamp();
        } else {
            $seconds = $this->started->diff($this->last_action)->s;
        }

        return mksize($diff / $seconds) . '/s';
    }

    protected function getShareRatio($peer)
    {
        if ($peer->downloaded) {
            $ratio = floor(($peer->uploaded / $peer->downloaded) * 1000) / 1000;
        } elseif ($peer->uploaded) {
            //@todo 读语言文件
            $ratio = '无限';
        } else {
            $ratio = '---';
        }

        return $ratio;
    }

    protected function getDownloadProgress($peer)
    {
        return sprintf("%.2f%%", 100 * (1 - ($peer->to_go / $peer->relative_torrent->size)));
    }

}
