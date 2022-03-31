<?php
namespace App\Repositories;

use App\Exceptions\ClientNotAllowedException;
use App\Models\AgentAllow;
use App\Models\AgentDeny;
use Nexus\Database\NexusDB;

class AgentAllowRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = AgentAllow::query();
        if (!empty($params['family'])) {
            $query->where('family', 'like', "%{$params['family']}%");
        }
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params)
    {
        $this->getPatternMatches($params['peer_id_pattern'], $params['peer_id_start'], $params['peer_id_match_num']);
        $this->getPatternMatches($params['agent_pattern'], $params['agent_start'], $params['agent_match_num']);
        $model = AgentAllow::query()->create($params);
        return $model;
    }

    public function update(array $params, $id)
    {
        $this->getPatternMatches($params['peer_id_pattern'], $params['peer_id_start'], $params['peer_id_match_num']);
        $this->getPatternMatches($params['agent_pattern'], $params['agent_start'], $params['agent_match_num']);
        $model = AgentAllow::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = AgentAllow::query()->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = AgentAllow::query()->findOrFail($id);
        $model->denies()->delete();
        $result = $model->delete();
        return $result;
    }

    public function getPatternMatches($pattern, $start, $matchNum)
    {
        if (!preg_match($pattern, $start, $matches)) {
            throw new ClientNotAllowedException(sprintf('pattern: %s can not match start: %s', $pattern, $start));
        }
        $matchCount = count($matches) - 1;
        //due to old data may be matchNum > matchCount
//        if ($matchNum > $matchCount && !IN_NEXUS) {
//            throw new ClientNotAllowedException("pattern: $pattern match start: $start got matches count: $matchCount, but require $matchNum.");
//        }
        return array_slice($matches, 1, $matchNum);
    }

    /**
     * @param $peerId
     * @param $agent
     * @param false $debug
     * @return \App\Models\NexusModel|mixed
     * @throws ClientNotAllowedException
     */
    public function checkClient($peerId, $agent, $debug = false)
    {
        //check from high version to low version, if high version allow, stop!
        $allows = NexusDB::remember("all_agent_allows", 600, function () {
            return AgentAllow::query()
                ->orderBy('peer_id_start', 'desc')
                ->orderBy('agent_start', 'desc')
                ->get();
        });
        $agentAllowPassed = null;
        $versionTooLowStr = '';
        foreach ($allows as $agentAllow) {
            $agentAllowId = $agentAllow->id;
            $logPrefix = "[ID: $agentAllowId]";
            $isPeerIdAllowed = $isAgentAllowed = $isPeerIdTooLow = $isAgentTooLow = false;
            //check peer_id, when handle scrape request, no peer_id, so let it pass
            if ($agentAllow->peer_id_pattern == '' || $peerId === null) {
                $isPeerIdAllowed = true;
            } else {
                $pattern = $agentAllow->peer_id_pattern;
                $start = $agentAllow->peer_id_start;
                $matchType = $agentAllow->peer_id_matchtype;
                $matchNum = $agentAllow->peer_id_match_num;
                try {
                    $peerIdResult = $this->isAllowed($pattern, $start, $matchNum, $matchType, $peerId, $debug, $logPrefix);
                    if ($debug) {
                        do_log(
                            "$logPrefix, peerIdResult: $peerIdResult, with parameters: "
                            . nexus_json_encode(compact('pattern', 'start', 'matchNum', 'matchType', 'peerId'))
                        );
                    }
                } catch (\Exception $exception) {
                    do_log("$logPrefix, check peer_id error: " . $exception->getMessage(), 'error');
                    throw new ClientNotAllowedException("regular expression err for peer_id: " . $start . ", please ask sysop to fix this");
                }
                if ($peerIdResult == 1) {
                    $isPeerIdAllowed = true;
                }
                if ($peerIdResult == 2) {
                    $isPeerIdTooLow = true;
                }
            }

            //check agent
            if ($agentAllow->agent_pattern == '') {
                $isAgentAllowed = true;
            } else {
                $pattern = $agentAllow->agent_pattern;
                $start = $agentAllow->agent_start;
                $matchType = $agentAllow->agent_matchtype;
                $matchNum = $agentAllow->agent_match_num;
                try {
                    $agentResult = $this->isAllowed($pattern, $start, $matchNum, $matchType, $agent, $debug, $logPrefix);
                    if ($debug) {
                        do_log(
                            "$logPrefix, agentResult: $agentResult, with parameters: "
                            . nexus_json_encode(compact('pattern', 'start', 'matchNum', 'matchType', 'agent'))
                        );
                    }
                } catch (\Exception $exception) {
                    do_log("$logPrefix, check agent error: " . $exception->getMessage(), 'error');
                    throw new ClientNotAllowedException("regular expression err for agent: " . $start . ", please ask sysop to fix this");
                }
                if ($agentResult == 1) {
                    $isAgentAllowed = true;
                }
                if ($agentResult == 2) {
                    $isAgentTooLow = true;
                }
            }

            //both OK, passed, client is allowed
            if ($isPeerIdAllowed && $isAgentAllowed) {
                $agentAllowPassed = $agentAllow;
                break;
            }
            if ($isPeerIdTooLow && $isAgentTooLow) {
                $versionTooLowStr = "Your " . $agentAllow->family . " 's version is too low, please update it after " . $agentAllow->start_name;
            }
        }

        if ($versionTooLowStr) {
            throw new ClientNotAllowedException($versionTooLowStr);
        }

        if (!$agentAllowPassed) {
            throw new ClientNotAllowedException("Banned Client, Please goto " . getSchemeAndHttpHost() . "/faq.php#id29 for a list of acceptable clients");
        }

        if ($debug) {
            do_log("agentAllowPassed: " . $agentAllowPassed->toJson());
        }

        // check if exclude
        if ($agentAllowPassed->exception == 'yes') {
            $agentDeny = $this->checkIsDenied($peerId, $agent, $agentAllowPassed->id);
            if ($agentDeny) {
                if ($debug) {
                    do_log("agentDeny: " . $agentDeny->toJson());
                }
                throw new ClientNotAllowedException(sprintf(
                    "[%s-%s]Client: %s is banned due to: %s",
                    $agentAllowPassed->id, $agentDeny->id, $agentDeny->name, $agentDeny->comment
                ));
            }
        }
        if (isHttps() && $agentAllowPassed->allowhttps != 'yes') {
            throw new ClientNotAllowedException(sprintf(
                "[%s]This client does not support https well, Please goto %s/faq.php#id29 for a list of proper clients",
                $agentAllowPassed->id, getSchemeAndHttpHost()
            ));
        }

        return $agentAllowPassed;

    }

    private function checkIsDenied($peerId, $agent, $familyId)
    {
        $agentDenies = AgentDeny::query()->where('family_id', $familyId)->get();
        foreach ($agentDenies as $agentDeny) {
            if ($agentDeny->agent == $agent && preg_match("/^" . $agentDeny->peer_id . "/", $peerId)) {
                return $agentDeny;
            }
        }
    }

    /**
     * check peer_id or agent is allowed
     *
     * 0: not allowed
     * 1: allowed
     * 2: version too low
     *
     * @param $pattern
     * @param $start
     * @param $matchNum
     * @param $matchType
     * @param $value
     * @param bool $debug
     * @param string $logPrefix
     * @return int
     * @throws ClientNotAllowedException
     */
    private function isAllowed($pattern, $start, $matchNum, $matchType, $value, $debug = false, $logPrefix = ''): int
    {
        $matchBench = $this->getPatternMatches($pattern, $start, $matchNum);
        if ($debug) {
            do_log("$logPrefix, matchBench: " . nexus_json_encode($matchBench));
        }
        if (!preg_match($pattern, $value, $matchTarget)) {
            if ($debug) {
                do_log(sprintf("$logPrefix, pattern: (%s) not match: (%s)", $pattern, $value));
            }
            return 0;
        }
        if ($matchNum <= 0) {
            return 1;
        }
        $matchTarget = array_slice($matchTarget, 1);
        if ($debug) {
            do_log("$logPrefix, matchTarget: " . nexus_json_encode($matchTarget));
        }
        for ($i = 0; $i < $matchNum; $i++) {
            if (!isset($matchBench[$i]) || !isset($matchTarget[$i])) {
                break;
            }
            if ($matchType == 'dec') {
                $matchBench[$i] = intval($matchBench[$i]);
                $matchTarget[$i] = intval($matchTarget[$i]);
            } elseif ($matchType == 'hex') {
                $matchBench[$i] = hexdec($matchBench[$i]);
                $matchTarget[$i] = hexdec($matchTarget[$i]);
            } else {
                throw new ClientNotAllowedException(sprintf("Invalid match type: %s", $matchType));
            }
            if ($matchTarget[$i] > $matchBench[$i]) {
                //higher, pass directly
                return 1;
            } elseif ($matchTarget[$i] < $matchBench[$i]) {
                return 2;
            }
        }

        //NOTE: at last, after all position checked, not [NOT_MATCH] or lower, it is passed!
        return 1;

    }

}
