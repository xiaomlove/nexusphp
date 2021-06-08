<?php

namespace Nexus\Field;

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
        global $lang_fields, $lang_functions;
        $trName = tr($lang_fields['col_name'] . '<font color="red">*</font>', '<input type="text" name="name" value="' . ($row['name'] ?? '') . '" style="width: 300px" />&nbsp;&nbsp;' . $lang_fields['col_name_help'], 1, '', true);
        $trLabel = tr($lang_fields['col_label'] . '<font color="red">*</font>', '<input type="text" name="label" value="' . ($row['label'] ?? '') . '"  style="width: 300px" />', 1, '', true);
        $trType = tr($lang_fields['col_type'] . '<font color="red">*</font>', $this->radio('type', $this->getTypeRadioOptions(), $row['type'] ?? null), 1, '', true);
        $trRequired = tr($lang_fields['col_required'] . '<font color="red">*</font>', $this->radio('required', ['0' => $lang_functions['text_no'], '1' => $lang_functions['text_yes']], $row['required'] ?? null), 1, '', true);
        $trHelp = tr($lang_fields['col_help'], '<textarea name="help" rows="4" cols="80">' . ($row['help'] ?? '') . '</textarea>', 1, '', true);
        $trOptions = tr($lang_fields['col_options'], '<textarea name="options" rows="6" cols="80">' . ($row['options'] ?? '') . '</textarea><br/>' . $lang_fields['col_options_help'], 1, '', true);
        $trIsSingleRow = tr($lang_fields['col_is_single_row'] . '<font color="red">*</font>', $this->radio('is_single_row', ['0' => $lang_functions['text_no'], '1' => $lang_functions['text_yes']], $row['is_single_row'] ?? null), 1, '', true);
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
        $sql = "select * from torrents_custom_fields order by id asc $limit";
        $res = sql_query($sql);
        $header = [
            'id' => $lang_fields['col_id'],
            'name' => $lang_fields['col_name'],
            'label' => $lang_fields['col_label'],
            'type_text' => $lang_fields['col_type'],
            'required_text' => $lang_fields['col_required'],
            'is_single_row_text' => $lang_fields['col_is_single_row'],
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

    public function renderOnUploadPage($torrentId = 0)
    {
        global $browsecatmode;
        $searchBox = NexusDB::getOne('searchbox', "id = $browsecatmode");
        if (empty($searchBox)) {
            throw new \RuntimeException("Invalid search box: $browsecatmode");
        }
        $customValues = $this->listTorrentCustomField($torrentId);
        $sql = sprintf('select * from torrents_custom_fields where id in (%s)', $searchBox['custom_fields'] ?: 0);
        $res = sql_query($sql);
        $html = '';
        while ($row = mysql_fetch_assoc($res)) {
            $name = "custom_fields[{$row['id']}]";
            $currentValue = $customValues[$row['id']]['custom_field_value'] ?? '';
            if ($row['type'] == self::TYPE_TEXT) {
                $html .= tr($row['label'], sprintf('<input type="text" name="%s" value="%s" style="width: 650px"/>', $name, $currentValue), 1);
            } elseif ($row['type'] == self::TYPE_TEXTAREA) {
                $html .= tr($row['label'], sprintf('<textarea name="%s" rows="4" style="width: 650px">%s</textarea>', $name, $currentValue), 1);
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
                $html .= tr($row['label'], $part, 1);
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
                $html .= tr($row['label'], $part, 1);
            } elseif ($row['type'] == self::TYPE_IMAGE) {
                $callbackFunc = "preview_custom_field_image_" . $row['id'];
                $iframeId = "iframe_$callbackFunc";
                $inputId = "input_$callbackFunc";
                $imgId = "attach" . $row['id'];
                $previewBoxId = "preview_$callbackFunc";
                $y = '<iframe id="' . $iframeId . '" src="' . getSchemeAndHttpHost() . '/attachment.php?callback_func=' . $callbackFunc . '" width="100%" height="24" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>';
                $y .= sprintf('<input id="%s" type="text" name="%s" value="%s" style="width: 650px;margin: 10px 0">', $inputId, $name, $currentValue);
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
                $html .= tr($row['label'], $y, 1);
            }
        }
        return $html;
    }

    public function listTorrentCustomField($torrentId, $searchBoxId = 0)
    {
        global $browsecatmode;
        if ($searchBoxId <= 0) {
            $searchBoxId = $browsecatmode;
        }
        //suppose torrentId is array
        $isArray = true;
        $torrentIdArr = $torrentId;
        if (!is_array($torrentId)) {
            $isArray = false;
            $torrentIdArr = [$torrentId];
        }
        $torrentIdStr = implode(',', $torrentIdArr);
        $res = sql_query("select f.*, v.custom_field_value, v.torrent_id from torrents_custom_field_values v inner join torrents_custom_fields f on v.custom_field_id = f.id inner join searchbox box on box.id = $searchBoxId and find_in_set(f.id, box.custom_fields) where torrent_id in ($torrentIdStr)");
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
                $values[$row['torrent_id']][$row['id']][] = $row['custom_field_value'];
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

    public function renderOnTorrentDetailsPage($torrentId)
    {
        global $browsecatmode;
        $displayName = get_searchbox_value($browsecatmode, 'custom_fields_display_name');
        $display = get_searchbox_value($browsecatmode, 'custom_fields_display');
        $customFields = $this->listTorrentCustomField($torrentId);
        $mixedRowContent = nl2br($display);
        $rowByRowHtml = '';
        foreach ($customFields as $field) {
            $content = $this->formatCustomFieldValue($field);
            $mixedRowContent = str_replace("<%{$field['name']}.label%>", $field['label'], $mixedRowContent);
            $mixedRowContent = str_replace("<%{$field['name']}.value%>", $content, $mixedRowContent);
            if ($field['is_single_row']) {
                $rowByRowHtml .= tr($field['label'], $content, 1);
            }
        }
        $result = $rowByRowHtml;
        if (!empty($mixedRowContent)) {
            $result .= tr($displayName, $mixedRowContent, 1);
        }
        return $result;
    }



    protected function formatCustomFieldValue(array $customFieldWithValue)
    {
        $result = '';
        $fieldValue = $customFieldWithValue['custom_field_value'];
        switch ($customFieldWithValue['type']) {
            case self::TYPE_TEXT:
            case self::TYPE_TEXTAREA:
                $result .= format_comment($fieldValue);
                break;
            case self::TYPE_IMAGE:
                if (substr($fieldValue, 0, 4) == 'http') {
                    $result .= formatImg($fieldValue, true, 700, 0, "attach{$customFieldWithValue['id']}");
                } else {
                    $result .= format_comment($fieldValue);
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




}
