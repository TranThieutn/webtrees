<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2019 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class TopPageViewsModule
 */
class TopPageViewsModule extends AbstractModule implements ModuleInterface, ModuleBlockInterface
{
    use ModuleBlockTrait;

    /**
     * How should this module be labelled on tabs, menus, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return I18N::translate('Most viewed pages');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Most viewed pages” module */
        return I18N::translate('A list of the pages that have been viewed the most number of times.');
    }

    /**
     * Generate the HTML content of this block.
     *
     * @param Tree     $tree
     * @param int      $block_id
     * @param string   $ctype
     * @param string[] $cfg
     *
     * @return string
     */
    public function getBlock(Tree $tree, int $block_id, string $ctype = '', array $cfg = []): string
    {
        $num             = $this->getBlockSetting($block_id, 'num', '10');
        $count_placement = $this->getBlockSetting($block_id, 'count_placement', 'before');

        extract($cfg, EXTR_OVERWRITE);

        $top10 = DB::table('hit_counter')
            ->where('gedcom_id', '=', $tree->id())
            ->whereIn('page_name', ['individual.php','family.php','source.php','repo.php','note.php','mediaviewer.php'])
            ->orderByDesc('page_count')
            ->limit((int) $num)
            ->pluck('page_count', 'page_parameter');

        $content = '<table>';
        foreach ($top10 as $id => $count) {
            $record = GedcomRecord::getInstance($id, $tree);
            if ($record && $record->canShow()) {
                $content .= '<tr>';
                if ($count_placement == 'before') {
                    $content .= '<td dir="ltr" style="text-align:right">[' . $count . ']</td>';
                }
                $content .= '<td class="name2" ><a href="' . e($record->url()) . '">' . $record->getFullName() . '</a></td>';
                if ($count_placement == 'after') {
                    $content .= '<td dir="ltr" style="text-align:right">[' . $count . ']</td>';
                }
                $content .= '</tr>';
            }
        }
        $content .= '</table>';

        if ($ctype !== '') {
            if ($ctype === 'gedcom' && Auth::isManager($tree)) {
                $config_url = route('tree-page-block-edit', [
                    'block_id' => $block_id,
                    'ged'      => $tree->name(),
                ]);
            } elseif ($ctype === 'user' && Auth::check()) {
                $config_url = route('user-page-block-edit', [
                    'block_id' => $block_id,
                    'ged'      => $tree->name(),
                ]);
            } else {
                $config_url = '';
            }

            return view('modules/block-template', [
                'block'      => str_replace('_', '-', $this->getName()),
                'id'         => $block_id,
                'config_url' => $config_url,
                'title'      => $this->title(),
                'content'    => $content,
            ]);
        }

        return $content;
    }

    /**
     * Should this block load asynchronously using AJAX?
     *
     * Simple blocks are faster in-line, more comples ones
     * can be loaded later.
     *
     * @return bool
     */
    public function loadAjax(): bool
    {
        return true;
    }

    /**
     * Can this block be shown on the user’s home page?
     *
     * @return bool
     */
    public function isUserBlock(): bool
    {
        return false;
    }

    /**
     * Can this block be shown on the tree’s home page?
     *
     * @return bool
     */
    public function isGedcomBlock(): bool
    {
        return true;
    }

    /**
     * Update the configuration for a block.
     *
     * @param Request $request
     * @param int     $block_id
     *
     * @return void
     */
    public function saveBlockConfiguration(Request $request, int $block_id)
    {
        $this->setBlockSetting($block_id, 'num', $request->get('num', '10'));
        $this->setBlockSetting($block_id, 'count_placement', $request->get('count_placement', 'before'));
    }

    /**
     * An HTML form to edit block settings
     *
     * @param Tree $tree
     * @param int  $block_id
     *
     * @return void
     */
    public function editBlockConfiguration(Tree $tree, int $block_id)
    {
        $num             = $this->getBlockSetting($block_id, 'num', '10');
        $count_placement = $this->getBlockSetting($block_id, 'count_placement', 'before');

        $options = [
            /* I18N: An option in a list-box */
            'before' => I18N::translate('before'),
            /* I18N: An option in a list-box */
            'after'  => I18N::translate('after'),
        ];

        echo view('modules/top10_pageviews/config', [
            'count_placement' => $count_placement,
            'num'             => $num,
            'options'         => $options,
        ]);
    }
}
