<?php
/**
 * Sélecteur d’indicatif : drapeau + code ISO + indicatif (compact).
 * Données : includes/data/countries_mledoze.json (mledoze/countries).
 */

if (!function_exists('phone_country_dial_variants')) {
    /**
     * @return list<string>
     */
    function phone_country_dial_variants(array $c): array {
        $codes = $c['callingCodes'] ?? [];
        $root = $c['idd']['root'] ?? '';

        if ($codes === []) {
            $sufs = $c['idd']['suffixes'] ?? [];
            if ($root !== '' && $sufs !== []) {
                $out = [];
                foreach ($sufs as $s) {
                    $r = $root[0] === '+' ? $root : '+' . ltrim($root, '+');
                    $out[] = $r . $s;
                }
                return $out;
            }
            return [];
        }

        $n = count($codes);
        if ($n > 10 && $root === '+1') {
            return ['+1'];
        }

        return $codes;
    }

    /**
     * @return list<array{dial:string,name:string,flag:string,iso:string}>
     */
    function phone_country_flat_entries(): array {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $path = __DIR__ . '/data/countries_mledoze.json';
        if (!is_readable($path)) {
            $cached = [
                ['dial' => '+221', 'name' => 'Sénégal', 'flag' => '🇸🇳', 'iso' => 'SN'],
                ['dial' => '+33', 'name' => 'France', 'flag' => '🇫🇷', 'iso' => 'FR'],
                ['dial' => '+1', 'name' => 'États-Unis', 'flag' => '🇺🇸', 'iso' => 'US'],
            ];
            return $cached;
        }

        $json = json_decode(file_get_contents($path), true);
        if (!is_array($json)) {
            $cached = [];
            return $cached;
        }

        $rows = [];
        foreach ($json as $c) {
            $iso = $c['cca2'] ?? '';
            if ($iso === '') {
                continue;
            }
            $name = $c['translations']['fra']['common'] ?? $c['name']['common'] ?? $iso;
            $flag = $c['flag'] ?? '';
            foreach (phone_country_dial_variants($c) as $dial) {
                $d = (string) $dial;
                if ($d === '') {
                    continue;
                }
                if ($d[0] !== '+') {
                    $d = '+' . ltrim($d, '+');
                }
                $rows[] = [
                    'dial' => $d,
                    'name' => $name,
                    'flag' => $flag,
                    'iso' => strtoupper($iso),
                ];
            }
        }

        usort($rows, function ($a, $b) {
            $cmp = strcmp($a['iso'], $b['iso']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['dial'], $b['dial']);
        });

        $cached = $rows;
        return $cached;
    }

    /**
     * Libellé court : drapeau + code pays + indicatif (ex. 🇸🇳 SN +221).
     */
    function phone_country_option_label(array $row): string {
        $flag = trim($row['flag'] ?? '');
        $iso = $row['iso'] ?? '';
        $d = $row['dial'] ?? '';
        return trim($flag . ' ' . $iso . ' ' . $d);
    }

    /**
     * @param string $selectedDial ex. +221
     * @param array<string, string> $extra_attrs
     */
    function render_phone_country_select(string $name, string $id, string $selectedDial = '+221', array $extra_attrs = []): void {
        $selectedDial = preg_replace('/[^\d+]/', '', (string) $selectedDial);
        if ($selectedDial !== '' && $selectedDial[0] !== '+') {
            $selectedDial = '+' . $selectedDial;
        }
        if ($selectedDial === '') {
            $selectedDial = '+221';
        }

        $attrs = '';
        foreach ($extra_attrs as $k => $v) {
            $attrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
        }

        $matchedDial = false;
        ?>
<select name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
        id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
        class="phone-country-select"<?php echo $attrs; ?>>
        <?php foreach (phone_country_flat_entries() as $row) :
            $d = $row['dial'];
            $label = phone_country_option_label($row);
            $pick = ($d === $selectedDial && !$matchedDial);
            if ($pick) {
                $matchedDial = true;
            }
            ?>
    <option value="<?php echo htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?>"
            data-iso="<?php echo htmlspecialchars($row['iso'], ENT_QUOTES, 'UTF-8'); ?>"
            title="<?php echo htmlspecialchars($row['name'] . ' — ' . $d, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $pick ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
</select>
        <?php
    }
}
