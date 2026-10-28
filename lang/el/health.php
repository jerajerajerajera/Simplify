<?php

return [
    'title' => 'Κατάσταση κεντρικών υπολογιστών',
    'refresh' => 'Έλεγχος ξανά',
    'hosts' => '{0} Κανένας κεντρικός υπολογιστής|{1} Απαντά :available από 1 κεντρικό υπολογιστή|[2,*] Απαντούν :available από :hosts κεντρικούς υπολογιστές',
    'state' => [
        'healthy' => 'Υγιής',
        'warning' => 'Υψηλό φορτίο',
        'critical' => 'Κρίσιμο φορτίο',
        'maintenance' => 'Συντήρηση',
        'partly' => 'Μερικώς μη διαθέσιμο',
        'unavailable' => 'Μη διαθέσιμο',
    ],
    'problem' => [
        'timeout' => 'Το Wings δεν απάντησε εγκαίρως',
        'connect' => 'Αδυναμία σύνδεσης στο Wings',
        'refused' => 'Το Wings απέρριψε το αίτημα (HTTP :status)',
        'unreachable' => 'Το Wings δεν είναι προσβάσιμο (HTTP :status)',
        'http' => 'Το Wings απάντησε με HTTP :status',
        'no_stats' => 'Το Wings δεν έστειλε χρήσιμους αριθμούς',
    ],
];
