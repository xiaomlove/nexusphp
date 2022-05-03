<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\Torrent;
use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexus\Nexus;

class TorrentResource extends JsonResource
{
    protected $imageTypes = ['image', 'attachment'];

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'filename' => $this->filename,
            'small_descr' => $this->small_descr,
            'comments' => $this->comments,
            'size_human' => mksize($this->size),
            'added' => $this->added->toDateTimeString(),
            'added_human' => $this->added->format('Y-m-d H:i'),
            'ttl' => $this->added->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE]),
            'leechers' => $this->leechers,
            'seeders' => $this->seeders,
            'times_completed' => $this->times_completed,
            'numfiles' => $this->numfiles,
            'sp_state' => $this->sp_state,
            'sp_state_real' => $this->sp_state_real,
            'promotion_info' => $this->promotionInfo,
            'hr' => $this->hr,
            'pick_type' => $this->picktype,
            'pick_time' => $this->picktime,
            'pick_info' => $this->pickInfo,
            'download_url' => $this->download_url,
            'user' => new UserResource($this->whenLoaded('user')),
            'anonymous' => $this->anonymous,
            'basic_category' => new CategoryResource($this->whenLoaded('basic_category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'thanks' => ThankResource::collection($this->whenLoaded('thanks')),
            'reward_logs' => RewardResource::collection($this->whenLoaded('reward_logs')),
        ];
        if ($this->cover) {
            $cover = $this->cover;
        } else {
            $descriptionArr = format_description($this->descr);
            $cover = get_image_from_description($descriptionArr, true);
        }
        $out['cover'] = resize_image($cover, 100, 100);
        if ($request->routeIs('torrents.show')) {
            if (!isset($descriptionArr)) {
                $descriptionArr = format_description($this->descr);
            }
            $baseInfo = [
                ['label' => nexus_trans('torrent.show.size'), 'value' => mksize($this->size)],
            ];
            foreach (Torrent::getBasicInfo() as $relation => $text) {
                if ($info = $this->whenLoaded($relation)) {
                    $baseInfo[] = ['label' => $text, 'value' => $info->name];
                }
            }
            $out['base_info'] = $baseInfo;

            $out['description'] = $descriptionArr;

            $out['images'] = get_image_from_description($descriptionArr);

            $out['thank_users_count'] = $this->thank_users_count;
            $out['peers_count'] = $this->peers_count;
            $out['reward_logs_count'] = $this->reward_logs_count;
        }
        if (nexus()->isPlatformAdmin()) {
            $out['details_url'] = sprintf('%s/details.php?id=%s', getSchemeAndHttpHost(), $this->id);
        }

//            $out['upload_peers_count'] = $this->upload_peers_count;
//            $out['download_peers_count'] = $this->download_peers_count;
//            $out['finish_peers_count'] = $this->finish_peers_count;

        return $out;

    }



}
