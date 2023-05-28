<?php

namespace Nexus\Field;

use App\Models\SearchBox;
use App\Models\Tag;
use App\Models\TorrentCustomField;
use App\Models\TorrentCustomFieldValue;
use Elasticsearch\Endpoints\Search;
use Nexus\Database\NexusDB;

class Field
{
    const TYPE_TEXT = 'text';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_RADIO = 'radio';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_SELECT = 'select';
    const TYPE_IMAGE = 'image';

    public static $types = [
        self::TYPE_TEXT => [
            'text' => 'text',
            'has_option' => false,
            'is_value_multiple' => false,
        ],
        self::TYPE_TEXTAREA => [
            'text' => 'textarea',
            'has_option' => false,
            'is_value_multiple' => false,
        ],
        self::TYPE_RADIO => [
            'text' => 'radio',
            'has_option' => true,
            'is_value_multiple' => false,
        ],
        self::TYPE_CHECKBOX => [
            'text' => 'checkbox',
            'has_option' => true,
            'is_value_multiple' => true,
        ],
        self::TYPE_SELECT => [
            'text' => 'select',
            'has_option' => true,
            'is_value_multiple' => false,
        ],
        self::TYPE_IMAGE => [
            'text' => 'image',
            'has_option' => false,
            'is_value_multiple' => false,
        ],
    ];

    private $preparedTorrentCustomFieldValues = [];

    public function getTypeHuman($type)
    {
        global $lang_fields;
        $map = [
            self::TYPE_TEXT => $lang_fields['field_type_text'],
            self::TYPE_TEXTAREA => $lang_fields['field_type_textarea'],
            self::TYPE_RADIO => $lang_fields['field_type_radio'],
            self::TYPE_CHECKBOX => $lang_fields['field_type_checkbox'],
            self::TYPE_SELECT => $lang_fields['field_type_select'],
            self::TYPE_IMAGE => $lang_fields['field_type_image'],
        ];
        return $map[$type] ?? '';
    }

    public function getTypeRadioOptions()
    {
        $out = [];
        foreach (self::$types as $key => $value) {
            $out[$key] = sprintf('%s(%s)', $value['text'], $this->getTypeHuman($key));
        }
        return $out;
    }


    public function radio($name, $options, $current = null)
    {
        $arr = [];
        foreach ($options as $value => $label) {
            $arr[] = sprintf(
                '<label style="margin-right: 4px;"><input type="radio" name="%s" value="%s"%s />%s</label>',
                $name, $value, (string)$current === (string)$value ? ' checked' : '', $label
            );
        }
        return implode('', $arr);
    }

    function buildFieldForm(array $row = [])
    {
        global $lang_fields, $lang_functions, $lang_catmanage;
        $trName = tr($lang_fields['col_name'] . '<font color="red">*</font>', '<input type="text" name="name" value="' . ($row['name'] ?? '') . '" style="width: 300px" />&nbsp;&nbsp;' . $lang_fields['col_name_help'], 1, '', true);
        $trLabel = tr($lang_fields['col_label'] . '<font color="red">*</font>', '<input type="text" name="label" value="' . ($row['label'] ?? '') . '"  style="width: 300px" />', 1, '', true);
        $trType = tr($lang_fields['col_type'] . '<font color="red">*</font>', $this->radio('type', $this->getTypeRadioOptions(), $row['type'] ?? null), 1, '', true);
        $trRequired = tr($lang_fields['col_required'] . '<font color="red">*</font>', $this->radio('required', ['0' => $lang_functions['text_no'], '1' => $lang_functions['text_yes']], $row['required'] ?? null), 1, '', true);
        $trHelp = tr($lang_fields['col_help'], '<textarea name="help" rows="4" cols="80">' . ($row['help'] ?? '') . '</textarea>', 1, '', true);
        $trOptions = tr($lang_fields['col_options'], '<textarea name="options" rows="6" cols="80">' . ($row['options'] ?? '') . '</textarea><br/>' . $lang_fields['col_options_help'], 1, '', true);
        $trIsSingleRow = tr($lang_fields['col_is_single_row'] . '<font color="red">*</font>', $this->radio('is_single_row', ['0' => $lang_functions['text_no'], '1' => $lang_functions['text_yes']], $row['is_single_row'] ?? null), 1, '', true);
        $trPriority = tr(nexus_trans('label.priority') . '<font color="red">*</font>', '<input type="number" name="priority" value="' . ($row['priority'] ?? '0') . '" style="width: 300px" />', 1, '', true);
        $trDisplay = tr($lang_fields['col_display'], '<textarea name="display" rows="4" cols="80">' . ($row['display'] ?? '') . '</textarea><br/>' . $lang_catmanage['row_custom_field_display_help'], 1, '', true);

        $id = $row['id'] ?? 0;
        $form = <<<HTML
<div>
<h1 align="center"><a class="faqlink" href="?action=view">{$lang_fields['text_field']}</a></h1>
<form method="post" action="fields.php?action=submit">
<div>
    <table border="1" cellspacing="0" cellpadding="10" width="100%">
            <input type="hidden" name="id" value="{$id}"/>
            {$trName}
            {$trLabel}
            {$trType}
            {$trRequired}
            {$trHelp}
            {$trOptions}
            {$trIsSingleRow}
            {$trPriority}
            {$trDisplay}
    </table>
</div>
<div style="text-align: center; margin-top: 10px;">
    <input type="submit" value="{$lang_fields['submit_submit']}" />
</div>
</form>
</div>
HTML;
        return $form;
    }

    function buildFieldTable()
    {
        global $lang_fields, $lang_functions;
        $perPage = 10;
        $total = get_row_count('torrents_custom_fields');
        list($paginationTop, $paginationBottom, $limit) = pager($perPage, $total, "?");
        $sql = "select * from torrents_custom_fields order by priority desc $limit";
        $res = sql_query($sql);
        $header = [
            'id' => $lang_fields['col_id'],
            'name' => $lang_fields['col_name'],
            'label' => $lang_fields['col_label'],
            'type_text' => $lang_fields['col_type'],
            'required_text' => $lang_fields['col_required'],
            'is_single_row_text' => $lang_fields['col_is_single_row'],
            'priority' => nexus_trans('label.priority'),
            'action' => $lang_fields['col_action'],
        ];
        $rows = [];
        while ($row = mysql_fetch_assoc($res)) {
            $row['required_text'] = $row['required'] ? $lang_functions['text_yes'] : $lang_functions['text_no'];
            $row['is_single_row_text'] = $row['is_single_row'] ? $lang_functions['text_yes'] : $lang_functions['text_no'];
            $row['type_text'] = sprintf('%s(%s)', $this->getTypeHuman($row['type']), $row['type']);
            $row['action'] = sprintf(
                "<a href=\"javascript:confirm_delete('%s', '%s', '');\">%s</a> | <a href=\"?action=edit&id=%s\">%s</a>",
                $row['id'], $lang_fields['js_sure_to_delete_this'], $lang_fields['text_delete'], $row['id'], $lang_fields['text_edit']
            );
            $rows[] = $row;
        }
        $head = <<<HEAD
<h1 align="center">{$lang_fields['field_management']}</h1>
<div style="margin-bottom: 8px;">
    <span id="add">
        <a href="?action=add" class="big"><b>{$lang_fields['text_add']}</b></a>
    </span>
</div>
HEAD;
        $table = $this->buildTable($header, $rows);
        return $head . $table . $paginationBottom;
    }

    public function save($data)
    {
        global $lang_functions, $lang_fields;
        $attributes = [];
        if (empty($data['name'])) {
            throw new \InvalidArgumentException("{$lang_fields['col_name']} {$lang_functions['text_required']}");
        }
        if (!preg_match('/^\w+$/', $data['name'])) {
            throw new \InvalidArgumentException("{$lang_fields['col_name']} {$lang_functions['text_invalid']}");
        }
        $attributes['name'] = $data['name'];

        if (empty($data['label'])) {
            throw new \InvalidArgumentException("{$lang_fields['col_label']} {$lang_functions['text_required']}");
        }
        $attributes['label'] = $data['label'];

        if (empty($data['type'])) {
            throw new \InvalidArgumentException("{$lang_fields['col_type']} {$lang_functions['text_required']}");
        }
        if (!isset(self::$types[$data['type']])) {
            throw new \InvalidArgumentException("{$lang_fields['col_type']} {$lang_functions['text_invalid']}");
        }
        $attributes['type'] = $data['type'];

        if (!isset($data['required'])) {
            throw new \InvalidArgumentException("{$lang_fields['col_required']} {$lang_functions['text_required']}");
        }
        if (!in_array($data['required'], ["0", "1"], true)) {
            throw new \InvalidArgumentException("{$lang_fields['col_name']} {$lang_functions['text_invalid']}");
        }
        $attributes['required'] = $data['required'];

        if (!isset($data['is_single_row'])) {
            throw new \InvalidArgumentException("{$lang_fields['col_is_single_row']} {$lang_functions['text_required']}");
        }
        if (!in_array($data['is_single_row'], ["0", "1"], true)) {
            throw new \InvalidArgumentException("{$lang_fields['col_is_single_row']} {$lang_functions['text_invalid']}");
        }
        $attributes['is_single_row'] = $data['is_single_row'];

        $attributes['help'] = $data['help'] ?? '';
        $attributes['options'] = trim($data['options'] ?? '');
        $attributes['display'] = trim($data['display'] ?? '');
        $attributes['priority'] = trim($data['priority'] ?? '0');
        $now = date('Y-m-d H:i:s');
        $attributes['updated_at'] = $now;
        $table = 'torrents_custom_fields';
        if (!empty($data['id'])) {
            $result = NexusDB::update($table, $attributes, "id = " . sqlesc($data['id']));
        } else {
            $attributes['created_at'] = $now;
            $result = NexusDB::insert($table, $attributes);
        }
        return $result;
    }

    protected function buildTable(array $header, array $rows)
    {
        $table = '<table border="1" cellspacing="0" cellpadding="5" width="100%"><thead><tr>';
        foreach ($header as $key => $value) {
            $table .= sprintf('<td class="colhead">%s</td>', $value);
        }
        $table .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $table .= '<tr>';
            foreach ($header as $headerKey => $headerValue) {
                $table .= sprintf('<td class="colfollow">%s</td>', $row[$headerKey] ?? '');
            }
            $table .= '</tr>';
        }
        $table .= '</tbody></table>';
        return $table;
    }

    public function buildFieldCheckbox($name, $current = [])
    {
        $sql = 'select * from torrents_custom_fields';
        $res = sql_query($sql);
        if (!is_array($current)) {
            $current = explode(',', $current);
        }
        $checkbox = '';
        while ($row = mysql_fetch_assoc($res)) {
            $checkbox .= sprintf(
                '<label style="margin-right: 4px;"><input type="checkbox" name="%s" value="%s"%s>%s</label>',
                $name, $row['id'], in_array($row['id'], $current) ? ' checked' : '', "{$row['name']}[{$row['label']}]"
            );
        }
        $checkbox .= '';
        return $checkbox;

    }

    public function renderOnUploadPage($torrentId = 0, $searchBoxId)
    {
        $searchBox = NexusDB::getOne('searchbox', "id = $searchBoxId");
        if (empty($searchBox)) {
            throw new \RuntimeException("Invalid search box: $searchBoxId");
        }
        $customValues = $this->listTorrentCustomField($torrentId, $searchBoxId);
        $sql = sprintf('select * from torrents_custom_fields where id in (%s) order by priority desc', $searchBox['custom_fields'] ?: 0);
        $res = sql_query($sql);
        $html = '';
        while ($row = mysql_fetch_assoc($res)) {
            $name = "custom_fields[$searchBoxId][{$row['id']}]";
            $currentValue = $customValues[$row['id']]['custom_field_value'] ?? '';
            $requireText = '';
            if ($row['required']) {
                $requireText = "<font color=\"red\">*</font>";
            }
            $trLabel = $row['label'] . $requireText;
            $trRelation = "mode_$searchBoxId";
            if ($row['type'] == self::TYPE_TEXT) {
                $html .= tr($trLabel, sprintf('<input type="text" name="%s" value="%s" style="width: %s"/>', $name, $currentValue, '99%'), 1, $trRelation);
            } elseif ($row['type'] == self::TYPE_TEXTAREA) {
                $html .= tr($trLabel, sprintf('<textarea name="%s" rows="4" style="width: %s">%s</textarea>', $name, '99%', $currentValue), 1, $trRelation);
            } elseif ($row['type'] == self::TYPE_RADIO || $row['type'] == self::TYPE_CHECKBOX) {
                if ($row['type'] == self::TYPE_CHECKBOX) {
                    $name .= '[]';
                }
                $part = "";
                foreach (preg_split('/[\r\n]+/', trim($row['options'])) as $option) {
                    if (empty($option) || ($pos = strpos($option, '|')) === false) {
                        continue;
                    }
                    $value = substr($option, 0, $pos);
                    $label = substr($option, $pos + 1);
                    $checked = "";
                    if ($row['type'] == self::TYPE_RADIO && (string)$currentValue === (string)$value) {
                        $checked = " checked";
                    }
                    if ($row['type'] == self::TYPE_CHECKBOX && in_array($value, (array)$currentValue)) {
                        $checked = " checked";
                    }
                    $part .= sprintf(
                        '<label style="margin-right: 4px"><input type="%s" name="%s" value="%s"%s />%s</label>',
                        $row['type'], $name, $value, $checked, $label
                    );
                }
                $html .= tr($trLabel, $part, 1, $trRelation);
            } elseif ($row['type'] == self::TYPE_SELECT) {
                $part = '<select name="' . $name . '">';
                foreach (preg_split('/[\r\n]+/', trim($row['options'])) as $option) {
                    if (empty($option) || ($pos = strpos($option, '|')) === false) {
                        continue;
                    }
                    $value = substr($option, 0, $pos);
                    $label = substr($option, $pos + 1);
                    $selected = "";
                    if (in_array($value, (array)$currentValue)) {
                        $selected = " selected";
                    }
                    $part .= sprintf(
                        '<option value="%s"%s>%s</option>',
                        $value, $selected, $label
                    );
                }
                $part .= '</select>';
                $html .= tr($trLabel, $part, 1, $trRelation);
            } elseif ($row['type'] == self::TYPE_IMAGE) {
                $callbackFunc = "preview_custom_field_image_" . $row['id'];
                $iframeId = "iframe_$callbackFunc";
                $inputId = "input_$callbackFunc";
                $imgId = "attach" . $row['id'];
                $previewBoxId = "preview_$callbackFunc";
                $y = '<iframe id="' . $iframeId . '" src="' . getSchemeAndHttpHost() . '/attachment.php?callback_func=' . $callbackFunc . '" width="100%" height="24" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>';
                $y .= sprintf('<input id="%s" type="text" name="%s" value="%s" style="width: %s;margin: 10px 0">', $inputId, $name, $currentValue, '99%');
                $y .= '<div id="' . $previewBoxId . '">';
                if (!empty($currentValue)) {
                    if (substr($currentValue, 0, 4) == 'http') {
                        $y .= formatImg($currentValue, true, 700, 0, $imgId);
                    } else {
                        $y .= format_comment($currentValue);
                    }
                }
                $y .= '</div>';
                $y .= <<<JS
<script>
    function {$callbackFunc}(delkey, url)
    {
        var previewBox = $('$previewBoxId')
        var existsImg = $('$imgId')
        var input = $('$inputId')
        if (existsImg) {
            previewBox.removeChild(existsImg)
            input.value = ''
        }
        var img = document.createElement('img')
        img.src=url
        img.setAttribute('onload', 'Scale(this, 700, 0);')
        img.setAttribute('onclick', 'Preview(this);')
        input.value = '[attach]' + delkey + '[/attach]'
        img.id='$imgId'
        previewBox.appendChild(img)
    }
</script>
JS;
                $html .= tr($trLabel, $y, 1, $trRelation, true);
            }
        }
        return $html;
    }

    public function listTorrentCustomField($torrentId, $searchBoxId)
    {
        //suppose torrentId is array
        $isArray = true;
        $torrentIdArr = $torrentId;
        if (!is_array($torrentId)) {
            $isArray = false;
            $torrentIdArr = [$torrentId];
        }
        $torrentIdStr = implode(',', $torrentIdArr);
        $res = sql_query("select f.*, v.custom_field_value, v.torrent_id from torrents_custom_field_values v inner join torrents_custom_fields f on v.custom_field_id = f.id inner join searchbox box on box.id = $searchBoxId and find_in_set(f.id, box.custom_fields) where torrent_id in ($torrentIdStr) order by f.priority desc");
        $values = [];
        $result = [];
        while ($row = mysql_fetch_assoc($res)) {
            $typeInfo = self::$types[$row['type']];
            if ($typeInfo['has_option']) {
                $options = preg_split('/[\r\n]+/', trim($row['options']));
                $optionsArr = [];
                foreach ($options as $option) {
                    $pos = strpos($option, '|');
                    $value = substr($option, 0, $pos);
                    $label = substr($option, $pos + 1);
                    $optionsArr[$value] = $label;
                }
                $row['options'] = $optionsArr;
            }
            $result[$row['torrent_id']][$row['id']] = $row;
            if ($typeInfo['is_value_multiple']) {
                $values[$row['torrent_id']][$row['id']] = json_decode($row['custom_field_value'], true);
            } else {
                $values[$row['torrent_id']][$row['id']] = $row['custom_field_value'];
            }
        }
        foreach ($result as $tid => &$fields) {
            foreach ($fields as &$field) {
                $field['custom_field_value'] = $values[$tid][$field['id']];
            }
        }
        return $isArray ? $result : ($result[$torrentId] ?? []);
    }

    public function renderOnTorrentDetailsPage($torrentId, $searchBoxId)
    {
        $displayName = get_searchbox_value($searchBoxId, 'custom_fields_display_name');
        $customFields = $this->listTorrentCustomField($torrentId, $searchBoxId);
        $mixedRowContent = get_searchbox_value($searchBoxId, 'custom_fields_display');
        $rowByRowHtml = '';
        $shouldRenderMixRow = false;
        foreach ($customFields as $field) {
            if (empty($field['custom_field_value'])) {
                //No value, remove special tags
                $mixedRowContent = str_replace("<%{$field['name']}.label%>", '', $mixedRowContent);
                $mixedRowContent = str_replace("<%{$field['name']}.value%>", '', $mixedRowContent);
                continue;
            }
            $shouldRenderMixRow = true;
            $contentNotFormatted = $this->formatCustomFieldValue($field, false);
            $mixedRowContent = str_replace("<%{$field['name']}.label%>", $field['label'], $mixedRowContent);
            $mixedRowContent = str_replace("<%{$field['name']}.value%>", $contentNotFormatted, $mixedRowContent);
            if ($field['is_single_row']) {
                if (!empty($field['display'])) {
                    $customFieldDisplay = $field['display'];
                    $customFieldDisplay = str_replace("<%{$field['name']}.label%>", $field['label'], $customFieldDisplay);
                    $customFieldDisplay = str_replace("<%{$field['name']}.value%>", $contentNotFormatted, $customFieldDisplay);
                    $rowByRowHtml .= tr($field['label'], format_comment($customFieldDisplay, false), 1);
                } else {
                    $contentFormatted = $this->formatCustomFieldValue($field, true);
                    $rowByRowHtml .= tr($field['label'], $contentFormatted, 1);
                }
            }
        }

        $result = $rowByRowHtml;
        if ($shouldRenderMixRow && $mixedRowContent) {
            $result .= tr($displayName, format_comment($mixedRowContent), 1);
        }
        return $result;
    }



    protected function formatCustomFieldValue(array $customFieldWithValue, $doFormatComment = false): string
    {
        $result = '';
        $fieldValue = $customFieldWithValue['custom_field_value'];
        switch ($customFieldWithValue['type']) {
            case self::TYPE_TEXT:
            case self::TYPE_TEXTAREA:
                $result .= $doFormatComment ? format_comment($fieldValue, false) : $fieldValue;
                break;
            case self::TYPE_IMAGE:
                if (substr($fieldValue, 0, 4) == 'http') {
                    $result .= $doFormatComment ? formatImg($fieldValue, true, 700, 0, "attach{$customFieldWithValue['id']}") : $fieldValue;
                } else {
                    $result .= $doFormatComment ? format_comment($fieldValue, false) : $fieldValue;
                }
                break;
            case self::TYPE_RADIO:
            case self::TYPE_CHECKBOX:
            case self::TYPE_SELECT;
                $fieldContent = [];
                foreach ((array)$fieldValue as $item) {
                    $fieldContent[] = $customFieldWithValue['options'][$item] ?? '';
                }
                $result .= implode(' ', $fieldContent);
                break;
            default:
                break;
        }
        return $result;
    }

    public function prepareTorrents(array $torrentIdArr)
    {
        $customFieldValues = $this->listTorrentCustomField($torrentIdArr);
        $result = [];
        foreach ($customFieldValues as $tid => &$customFields) {
            foreach ($customFields as &$field) {
                $field['custom_field_value_formatted'] = $this->formatCustomFieldValue($field);
                $result[$tid][$field['name']] = $field;
            }
        }
        $this->preparedTorrentCustomFieldValues = $result;
    }

    public function getPreparedTorrent($torrentId = null, $fieldName = null)
    {
        if (is_null($torrentId)) {
            return $this->preparedTorrentCustomFieldValues;
        }
        if (is_null($fieldName)) {
            return $this->preparedTorrentCustomFieldValues[$torrentId] ?? [];
        }
        return $this->preparedTorrentCustomFieldValues[$torrentId][$fieldName] ?? '';
    }


    public function saveFieldValues($searchBoxId, $torrentId, array $data)
    {
        $searchBox = SearchBox::query()->findOrFail($searchBoxId);
        $enabledFields = TorrentCustomField::query()->find($searchBox->custom_fields);
        $insert = [];
        $now = now();
        foreach ($enabledFields as $field) {
            if (empty($data[$field->id])) {
                if ($field->required) {
//                    throw new \InvalidArgumentException(nexus_trans("nexus.require_argument", ['argument' => $field->label]));
                    do_log("Field: {$field->label} required, but empty");
                }
                continue;
            }
            $insert[] = [
                'torrent_id' => $torrentId,
                'custom_field_id' => $field->id,
                'custom_field_value' => is_array($data[$field->id]) ? json_encode($data[$field->id]) : $data[$field->id],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        TorrentCustomFieldValue::query()->where('torrent_id', $torrentId)->delete();
        TorrentCustomFieldValue::query()->insert($insert);
    }



}
