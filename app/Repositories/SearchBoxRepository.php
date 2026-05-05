<?php

namespace App\Repositories;

use App\Exceptions\InsufficientPermissionException;
use App\Http\Middleware\Locale;
use App\Models\Category;
use App\Models\Icon;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use Filament\Forms;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Nexus\Database\NexusDB;

class SearchBoxRepository extends BaseRepository
{
    public function getList(array $params): LengthAwarePaginator
    {
        $query = SearchBox::query();
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    public function store(array $params)
    {
        $result = SearchBox::query()->create($params);

        return $result;
    }

    public function update(array $params, $id)
    {
        $result = SearchBox::query()->findOrFail($id);
        $result->update($params);

        return $result;
    }

    public function getDetail($id)
    {
        $result = SearchBox::query()->findOrFail($id);

        return $result;
    }

    public function delete($id)
    {
        $result = SearchBox::query()->findOrFail($id);
        $success = $result->delete();

        return $success;
    }

    public function listIcon(array $idArr)
    {
        $searchBoxList = SearchBox::query()->with('categories')->find($idArr);
        if ($searchBoxList->isEmpty()) {
            return $searchBoxList;
        }
        $iconIdArr = [];
        foreach ($searchBoxList as $value) {
            foreach ($value->categories as $category) {
                $iconId = $category->icon_id;
                if (! isset($iconIdArr[$iconId])) {
                    $iconIdArr[$iconId] = $iconId;
                }
            }
        }

        return Icon::query()->find(array_keys($iconIdArr));
    }

    public function migrateToModeRelated()
    {
        $searchBoxList = SearchBox::query()->get();
        foreach ($searchBoxList as $searchBox) {
            $taxonomies = [];
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $searchBoxField = 'show'.$torrentField;
                if ($searchBox->showsubcat && $searchBox->{$searchBoxField}) {
                    $taxonomies[] = [
                        'torrent_field' => $torrentField,
                        'display_text' => [
                            'en' => nexus_trans("searchbox.sub_category_{$torrentField}_label", [], Locale::$languageMaps['en']),
                            'chs' => nexus_trans("searchbox.sub_category_{$torrentField}_label", [], Locale::$languageMaps['chs']),
                            'cht' => nexus_trans("searchbox.sub_category_{$torrentField}_label", [], Locale::$languageMaps['cht']),
                        ],
                    ];
                }
            }
            if (! empty($taxonomies)) {
                $searchBox->update(['extra->'.SearchBox::EXTRA_TAXONOMY_LABELS => $taxonomies]);
            }
            clear_search_box_cache($searchBox->id);
        }
    }

    public function renderTaxonomySelect($searchBox, array $torrentInfo = []): string
    {
        if (! $searchBox instanceof SearchBox) {
            $searchBox = SearchBox::get(intval($searchBox));
        }
        $results = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomy) {
                $select = $this->buildTaxonomySelect($searchBox, $taxonomy['torrent_field'], $torrentInfo);
                if ($select) {
                    $results[] = $select;
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $select = $this->buildTaxonomySelect($searchBox, $torrentField, $torrentInfo);
                if ($select) {
                    $results[] = $select;
                }
            }
        }

        return implode('&nbsp;&nbsp;', $results);
    }

    public function listTaxonomyInfo($searchBox, array $torrentWithTaxonomy): array
    {
        if (! $searchBox instanceof SearchBox) {
            $searchBox = SearchBox::get(intval($searchBox));
        }
        $results = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $item) {
                $taxonomy = $this->getTaxonomyInfo($searchBox, $torrentWithTaxonomy, $item['torrent_field']);
                if ($taxonomy) {
                    $results[] = $taxonomy;
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $taxonomy = $this->getTaxonomyInfo($searchBox, $torrentWithTaxonomy, $torrentField);
                if ($taxonomy) {
                    $results[] = $taxonomy;
                }
            }
        }

        return $results;
    }

    private function getTaxonomyInfo(SearchBox $searchBox, array $torrentWithTaxonomy, $torrentField)
    {
        $searchBoxField = 'show'.$torrentField;
        $torrentTaxonomyField = $torrentField.'_name';
        if ($searchBox->showsubcat && $searchBox->{$searchBoxField} && ! empty($torrentWithTaxonomy[$torrentTaxonomyField])) {
            return [
                'field' => $torrentField,
                'label' => $searchBox->getTaxonomyLabel($torrentField),
                'value' => $torrentWithTaxonomy[$torrentTaxonomyField],
            ];
        }
    }

    private function buildTaxonomySelect(SearchBox $searchBox, $torrentField, array $torrentInfo)
    {
        $searchBoxId = $searchBox->id;
        $searchBoxField = 'show'.$torrentField;
        if ($searchBox->showsubcat && $searchBox->{$searchBoxField}) {
            $table = SearchBox::$taxonomies[$torrentField]['table'];
            $select = sprintf('<b>%s: </b>', $searchBox->getTaxonomyLabel($torrentField));
            $select .= sprintf('<select name="%s_sel[%s]" data-mode="%s_%s">', $torrentField, $searchBoxId, $torrentField, $searchBoxId);
            $select .= sprintf('<option value="%s">%s</option>', 0, nexus_trans('nexus.select_one_please'));
            $list = NexusDB::table($table)->where(function (Builder $query) use ($searchBox) {
                return $query->where('mode', $searchBox->id)->orWhere('mode', 0);
            })->orderBy('sort_index', 'desc')->get();
            foreach ($list as $item) {
                $selected = '';
                if (isset($torrentInfo[$torrentField]) && $torrentInfo[$torrentField] == $item->id) {
                    $selected = ' selected';
                }
                $select .= sprintf('<option value="%s"%s>%s</option>', $item->id, $selected, $item->name);
            }
            $select .= '</select>';

            return $select;
        }
    }

    public function listTaxonomyFormSchema($searchBox): array
    {
        if (! $searchBox instanceof SearchBox) {
            $searchBox = SearchBox::get(intval($searchBox));
        }
        $results = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomy) {
                $select = $this->buildTaxonomyFormSchema($searchBox, $taxonomy['torrent_field']);
                if ($select) {
                    $results[] = $select;
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $select = $this->buildTaxonomyFormSchema($searchBox, $torrentField);
                if ($select) {
                    $results[] = $select;
                }
            }
        }

        return $results;
    }

    private function buildTaxonomyFormSchema(SearchBox $searchBox, $torrentField)
    {
        $searchBoxId = $searchBox->id;
        $searchBoxField = 'show'.$torrentField;
        $name = sprintf('%s.%s', $torrentField, $searchBoxId);
        if ($searchBox->showsubcat && $searchBox->{$searchBoxField}) {
            $items = SearchBox::listTaxonomyItems($searchBox, $torrentField);

            return Forms\Components\Select::make($name)
                ->options($items->pluck('name', 'id')->toArray())
                ->label($searchBox->getTaxonomyLabel($torrentField));
        }
    }

    public function deleteCategory($id)
    {
        if (get_user_class() < User::CLASS_SYSOP) {
            throw new InsufficientPermissionException;
        }
        $idArr = Arr::wrap($id);
        $exists = Torrent::query()->whereHas('basic_category', function (\Illuminate\Database\Eloquent\Builder $query) use ($idArr) {
            return $query->whereIn('id', $idArr);
        })->exists();
        if ($exists) {
            throw new \RuntimeException('There are torrents that belong to this category and cannot be deleted!');
        }

        return Category::query()->whereIn('id', $idArr)->delete();
    }

    public function listSections($id, $withCategoryAndTags = true)
    {
        $searchBoxList = SearchBox::query()->with($withCategoryAndTags ? ['categories'] : [])->find($id);
        if ($withCategoryAndTags) {
            foreach ($searchBoxList as $searchBox) {
                if ($searchBox->showsubcat) {
                    $searchBox->loadSubCategories();
                }
                $searchBox->loadTags();
            }
        }

        return $searchBoxList;
    }

    // @phpstan-ignore-next-line class.notFound
    public function buildSearchBoxFormSchema(SearchBox $searchBox, string $namePrefix): Forms\Components\Section
    {
        $lang = get_langfolder_cookie();
        $heading = $searchBox->section_name[$lang] ?? nexus_trans('searchbox.sections.browse');

        // @phpstan-ignore-next-line class.notFound
        return Forms\Components\Section::make($heading)
            ->schema($this->buildCategoryTaxonomyTagSchema($searchBox, false, $namePrefix));
    }

    public function buildCategoryTaxonomyTagSchema(SearchBox $searchBox, bool $multiple, string $namePrefix): array
    {
        $schema = [];
        $mode = $searchBox->id;
        $namePrefix .= ".section.$mode";
        if ($multiple) {
            $schema[] = Forms\Components\CheckboxList::make("$namePrefix.category")
                ->options($searchBox->categories()->orderBy('sort_index', 'desc')->orderBy('id')->pluck('name', 'id'))
                ->label(nexus_trans('label.search_box.category'))
                ->columns(6);
        } else {
            $schema[] = Forms\Components\Radio::make("$namePrefix.category")
                ->options($searchBox->categories()->orderBy('sort_index', 'desc')->orderBy('id')->pluck('name', 'id'))
                ->label(nexus_trans('label.search_box.category'))
                ->columns(6);
        }

        // @phpstan-ignore-next-line class.notFound
        $fieldset = Forms\Components\Fieldset::make(nexus_trans('searchbox.sub_categories_label'));
        $fieldsetSchema = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomy) {
                $torrentField = $taxonomy['torrent_field'];
                $showField = 'show'.$torrentField;
                if ($searchBox->showsubcat && $searchBox->{$showField}) {
                    if ($multiple) {
                        $fieldsetSchema[] = Forms\Components\CheckboxList::make("$namePrefix.$torrentField")
                            ->options($this->listTaxonomies($torrentField, $mode))
                            ->label($searchBox->getTaxonomyLabel($torrentField))
                            ->columns(6);
                    } else {
                        $fieldsetSchema[] = Forms\Components\Radio::make("$namePrefix.$torrentField")
                            ->options($this->listTaxonomies($torrentField, $mode))
                            ->label($searchBox->getTaxonomyLabel($torrentField))
                            ->columns(6);
                    }
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $showField = 'show'.$torrentField;
                if ($searchBox->showsubcat && $searchBox->{$showField}) {
                    $fieldsetSchema[] = Forms\Components\CheckboxList::make("$namePrefix.$torrentField")
                        ->options($this->listTaxonomies($torrentField, $mode))
                        ->label($searchBox->getTaxonomyLabel($torrentField))
                        ->columns(6);
                }
            }
        }
        $fieldset->schema($fieldsetSchema)->columns(1);
        $schema[] = $fieldset;

        $tagRep = new TagRepository;
        $tags = $tagRep->listAll($searchBox->id);
        $schema[] = Forms\Components\CheckboxList::make("$namePrefix.tag")
            ->options($tags->pluck('name', 'id'))
            ->label(nexus_trans('label.tag.label'))
            ->columns(6);

        return $schema;
    }

    private function listTaxonomies($torrentField, $mode)
    {
        $tableName = SearchBox::$taxonomies[$torrentField]['table'];

        return NexusDB::table($tableName)
            ->where(function (Builder $query) use ($mode) {
                return $query->where('mode', $mode)->orWhere('mode', 0);
            })
            ->orderBy('sort_index', 'desc')
            ->orderBy('id', 'desc')
            ->pluck('name', 'id');
    }
}
