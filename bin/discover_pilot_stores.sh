#!/bin/bash
# Pilot Store ID Discovery Script
# Retrieves actual outlet IDs for pilot stores (Botany, Browns Bay, Glenfield)

cd "$(dirname "$0")/../transfer_engine" || exit 1

echo "Discovering Pilot Store IDs..."
echo ""

php -r "
require 'config/bootstrap.php';

use Unified\Integration\{VendConnection, VendAdapter};
use Unified\Support\{Logger, CacheManager};

\$logger = new Logger('logs/');
\$cache = new CacheManager(['enabled' => false]);
\$vendConnection = new VendConnection(require 'config/vend.php');
\$vendAdapter = new VendAdapter(\$vendConnection, \$logger, \$cache);

\$pilotStoreNames = ['Botany', 'Browns Bay', 'Glenfield'];

echo \"Searching for pilot stores...\n\n\";

\$outlets = \$vendAdapter->getOutlets();
\$found = [];

foreach (\$outlets as \$outlet) {
    if (in_array(\$outlet['name'], \$pilotStoreNames)) {
        \$found[] = \$outlet;
        echo \"✓ Found: \" . \$outlet['name'] . \"\n\";
        echo \"  ID: \" . \$outlet['id'] . \"\n\";
        echo \"  Code: \" . \$outlet['outlet_code'] . \"\n\n\";
    }
}

if (count(\$found) === count(\$pilotStoreNames)) {
    echo \"All pilot stores found!\n\n\";
    echo \"Update config/pilot_stores.php with these IDs:\n\n\";
    echo \"'pilot_stores' => [\n\";
    foreach (\$found as \$outlet) {
        echo \"    '\" . \$outlet['id'] . \"', // \" . \$outlet['name'] . \"\n\";
    }
    echo \"],\n\";
} else {
    echo \"Warning: Not all pilot stores found.\n\";
    echo \"Found \" . count(\$found) . \" of \" . count(\$pilotStoreNames) . \" stores.\n\";
}
"

echo ""
echo "Store discovery complete."
