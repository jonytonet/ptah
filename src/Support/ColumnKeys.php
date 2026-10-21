<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Every `cols*` key the package's RUNTIME reads from a screen config (1.47.0).
 * A key only a wizard writes, or only a command displays, is not here — the
 * doctor reports it as unread, which is the truth.
 *
 * A key the runtime does not read is ignored without a word: `colsHelper`
 * written where `colsHelpText` was meant, a `colsUploadDisk` the docs
 * promised and nothing implemented. `ptah:config:doctor` checks configs
 * against this list, and ColumnKeysTest keeps it equal to the keys the
 * code actually reads — in both directions.
 */
final class ColumnKeys
{
    /** @var list<string> */
    public const KNOWN = [
        'colsAlign', 'colsCellClass', 'colsCellIcon', 'colsCellStyle', 'colsColor',
        'colsConfirmMessage', 'colsCss', 'colsDefaultValue', 'colsEditableForm', 'colsFilterType',
        'colsFormBlock', 'colsGravar', 'colsHelpText', 'colsHelper', 'colsIcon', 'colsIsFilterable',
        'colsMask', 'colsMaskTransform', 'colsMetodoCustom', 'colsMetodoRaw', 'colsMinWidth',
        'colsNomeFisico', 'colsNomeLogico', 'colsOn', 'colsOnChange', 'colsOperator', 'colsOrderBy',
        'colsPermission', 'colsPlaceholder', 'colsRelacao', 'colsRelacaoBusca', 'colsRelacaoExibe',
        'colsRelacaoNested', 'colsRelation', 'colsRenderer', 'colsRendererBadges',
        'colsRendererBoolFalse', 'colsRendererBoolTrue', 'colsRendererColor',
        'colsRendererCurrency', 'colsRendererDecimals', 'colsRendererDurationUnit',
        'colsRendererFormat', 'colsRendererImageHeight', 'colsRendererImageWidth',
        'colsRendererLinkLabel', 'colsRendererLinkNewTab', 'colsRendererLinkTemplate',
        'colsRendererLocale', 'colsRendererMax', 'colsRendererMaxChars', 'colsRendererQrSize',
        'colsRequired', 'colsReverse', 'colsSDArraySearch', 'colsSDDependsOn', 'colsSDFilterColumn',
        'colsSDFilters', 'colsSDInitWithData', 'colsSDLabel', 'colsSDLabelField',
        'colsSDLabelThree', 'colsSDLabelTwo', 'colsSDLimit', 'colsSDMaskOne', 'colsSDMaskThree',
        'colsSDMaskTwo', 'colsSDMode', 'colsSDModel', 'colsSDOrder', 'colsSDOrderBy',
        'colsSDPlaceholder', 'colsSDService', 'colsSDServiceMethod', 'colsSDStartList',
        'colsSDTipo', 'colsSDValor', 'colsSDValueField', 'colsSdLimit', 'colsSdOrderBy',
        'colsSdSelectColumn', 'colsSdTable', 'colsSdValueColumn', 'colsSearchable', 'colsSelect',
        'colsSortable', 'colsSource', 'colsTable', 'colsTipo', 'colsUploadAllowedTypes',
        'colsUploadMaxSize', 'colsUploadPath', 'colsValidations', 'colsValue', 'colsVisibleList',
    ];

    /** The values the legacy `colsHelper` maps to a renderer (HasCrudRenderers). */
    public const HELPERS = ['dateFormat', 'dateTimeFormat', 'currencyFormat', 'yesOrNot', 'flagChannel'];

    public static function isKnown(string $key): bool
    {
        return in_array($key, self::KNOWN, true);
    }

    /** The closest known key, for a "did you mean" — null when nothing is close. */
    public static function closest(string $key): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach (self::KNOWN as $known) {
            $distance = levenshtein(strtolower($key), strtolower($known));
            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$known, $distance];
            }
        }

        return $bestDistance <= 3 ? $best : null;
    }
}
