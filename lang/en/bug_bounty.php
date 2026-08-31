<?php

return [
    'program_name' => 'Huwutong Bug Bounty Program',
    'scope' => [
        'api.huwutong.com',
        '*.huwutong.com',
        'SDK packages (js-sdk, python-sdk, java-sdk)',
        'Mobile API endpoints',
    ],
    'out_of_scope' => [
        'Self-XSS that cannot be escalated',
        'Social engineering attacks',
        'Denial of Service attacks',
        'Physical attacks on infrastructure',
        'Third-party services not controlled by Huwutong (e.g. GitHub, AWS)',
        'Rate limiting bypass without demonstrated harm',
        'Missing HTTP security headers without exploitability',
    ],
    'rewards' => [
        'critical' => 'Critical: $500 – $2,000+',
        'high' => 'High: $200 – $500',
        'medium' => 'Medium: $100 – $200',
        'low' => 'Low: $50 – $100',
        'informational' => 'Informational: No cash reward (Hall of Fame only)',
    ],
    'rules' => [
        'Provide clear steps to reproduce with a PoC when possible',
        'Allow reasonable time for a fix (typically 90 days for critical issues)',
        'Do not access or modify user data without permission',
        'Report privately — do not disclose publicly before a fix',
        'Only test on your own accounts',
        'One report per vulnerability (duplicates will be marked as such)',
        'No automated scanning without prior approval',
    ],
    'response_time' => 'We aim to acknowledge receipt within 48 hours and provide an initial assessment within 5 business days.',
    'disclosure_policy' => 'We practice coordinated disclosure. After a fix is deployed, public disclosure is typically allowed after 30 days.',
    'legal_safe_harbor' => 'We will not pursue legal action against researchers who act in good faith and follow this policy.',
    'severity' => [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        'informational' => 'Info',
    ],
    'rank' => [
        'gold' => 'Gold',
        'silver' => 'Silver',
        'bronze' => 'Bronze',
        'honorable' => 'Honorable',
    ],
];
