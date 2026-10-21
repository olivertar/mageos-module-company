<?php
// phpcs:ignoreFile -- standalone CLI test script, not module code
/**
 * MageOS_Company end-to-end test. Run it through run.sh (it sends mail to Mailpit, not the store's SMTP server):
 *   bash app/code/MageOS/Company/Test/E2E/run.sh
 *
 * Storefront, REST and GraphQL through real HTTP requests; mails read from Mailpit; admin grid SQL probes in-process.
 * Every fixture is removed at the end. Exit code 0 when every check passes.
 *
 * Environment:
 *   E2E_BASE_URL  storefront URL (default: secure base URL of the default store)
 *   E2E_MAILPIT   Mailpit URL (default: http://mailbox:8025)
 *
 * Needs: admin user ID 1 (an admin REST token is issued for it and revoked at the end), the
 * company.message.notify consumer, and the default company attributes and registration settings.
 */
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;

require dirname(__DIR__, 6) . '/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
$db = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();

define('BASE', rtrim(getenv('E2E_BASE_URL') ?: (string)$om->get(\Magento\Store\Model\StoreManagerInterface::class)
    ->getDefaultStoreView()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true), '/') . '/');
define('MAIL', rtrim(getenv('E2E_MAILPIT') ?: 'http://mailbox:8025', '/') . '/api/v1/');
const PASS = 'E2e-Passw0rd!x';

$u = (string)time();
$pass = 0;
$fail = 0;
$fixtures = ['companies' => [], 'customers' => [], 'emails' => []];

function check(string $label, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  OK   " : "  FAIL ") . $label . ($ok || $extra === '' ? '' : " -> $extra") . "\n";
}

function section(string $title): void
{
    echo "\n== $title\n";
}

final class Http
{
    private string $jar;
    /** @var array<string,string> */
    public array $headers = [];
    public int $code = 0;
    public string $location = '';

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'e2ejar');
    }

    /**
     * @param array<string,mixed>|string|null $body
     * @param string[] $headers
     */
    public function request(string $method, string $url, array|string|null $body = null, array $headers = []): string
    {
        $ch = curl_init(str_starts_with($url, 'http') ? $url : BASE . ltrim($url, '/'));
        $this->headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $this->headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        }
        $out = (string)curl_exec($ch);
        $this->code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        /** @var array<string,string> $received */
        $received = $this->headers;
        $this->location = $received['location'] ?? '';
        curl_close($ch);

        return $out;
    }

    public function get(string $url): string
    {
        return $this->request('GET', $url);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function post(string $url, array $data): string
    {
        return $this->request('POST', $url, $data);
    }

    public function formKey(string $url): string
    {
        $html = $this->get($url);
        preg_match('/name="form_key" type="hidden" value="([^"]+)"/', $html, $m);

        return $m[1] ?? '';
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<mixed>
     */
    public function json(string $method, string $url, ?array $body = null, ?string $token = null): array
    {
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $out = $this->request($method, $url, $body === null ? null : json_encode($body), $headers);

        return (array)json_decode($out, true);
    }
}

/**
 * @return array<int,array<string,mixed>>
 */
function mails(string $to): array
{
    $res = json_decode((string)file_get_contents(MAIL . 'search?query=' . rawurlencode('to:"' . $to . '"')), true);

    return $res['messages'] ?? [];
}

/**
 * @return array<string,mixed>|null
 */
function waitMail(string $to, string $subjectPart, int $tries = 20): ?array
{
    for ($i = 0; $i < $tries; $i++) {
        foreach (mails($to) as $m) {
            if (str_starts_with($m['Subject'], $subjectPart)) {
                return json_decode((string)file_get_contents(MAIL . 'message/' . $m['ID']), true);
            }
        }
        usleep(500000);
    }

    return null;
}

function tokenFrom(string $html, string $route): string
{
    $html = html_entity_decode($html);
    preg_match('#' . preg_quote($route, '#') . '[^"\s]*?[?&]token=([A-Za-z0-9._-]+)#', $html, $m);

    return $m[1] ?? '';
}

/**
 * @return array<string,mixed>|null
 */
function company(string $email): ?array
{
    global $db;
    $row = $db->fetchRow(
        'SELECT e.* FROM company_entity e WHERE e.email = ?',
        [$email]
    );

    return $row ?: null;
}

function runConsumer(): void
{
    exec('cd ' . escapeshellarg(BP) . ' && timeout 25 php bin/magento queue:consumers:start company.message.notify --single-thread --max-messages=20 2>&1');
}

/**
 * @param array<string,mixed> $extra
 * @return array<string,mixed>
 */
function register(Http $http, string $u, string $tag, array $extra = []): array
{
    $key = $http->formKey('company/register/');
    $data = [
        'form_key' => $key,
        'company' => [
            'name' => "E2E <b>$tag</b> $u",
            'email' => "e2e-$u-$tag-co@example.com",
            'legal_name' => "E2E $tag Legal $u",
            'vat_id' => "VAT$u$tag",
            'phone' => '+34 600 000 000',
        ],
        'address' => [
            'firstname' => 'Ana',
            'lastname' => 'Tester',
            'street' => ['Calle Mayor 1'],
            'city' => 'Madrid',
            'country_id' => 'ES',
            'region_id' => '',
            'region' => 'Madrid',
            'postcode' => '28001',
            'telephone' => '+34 600 000 001',
        ],
        'firstname' => 'Ana',
        'lastname' => "Owner$tag",
        'email' => "e2e-$u-$tag-owner@example.com",
        'password' => PASS,
        'password_confirmation' => PASS,
    ];
    $data = array_replace_recursive($data, $extra);
    $http->post('company/register/post/', $data);

    return $data;
}

function login(Http $http, string $email): void
{
    $key = $http->formKey('customer/account/login/');
    $http->post('customer/account/loginPost/', [
        'form_key' => $key,
        'login' => ['username' => $email, 'password' => PASS],
    ]);
}

// admin REST token
$tokenParamsFactory = $om->get(\Magento\Integration\Api\Data\UserTokenParametersInterfaceFactory::class);
$adminContext = new \Magento\Integration\Model\CustomUserContext(
    1,
    \Magento\Authorization\Model\UserContextInterface::USER_TYPE_ADMIN
);
$adminToken = $om->get(\Magento\Integration\Api\UserTokenIssuerInterface::class)
    ->create($adminContext, $tokenParamsFactory->create());

try {
    $a = new Http();
    $b = new Http();
    $anon = new Http();

    // ------------------------------------------------------------------
    section('Registration: CSRF and mass assignment');
    $anon->get('company/register/');
    $anon->post('company/register/post/', [
        'company' => ['name' => "E2E csrf $u", 'email' => "e2e-$u-csrf-co@example.com"],
        'email' => "e2e-$u-csrf-owner@example.com",
    ]);
    check('POST without form_key does not create a company', company("e2e-$u-csrf-co@example.com") === null);

    $regA = register($a, $u, 'a', ['company' => [
        'status' => 1, 'company_id' => 'HACK', 'erp_code' => 'HACK', 'super_user_id' => 1, 'website_id' => 99, 'entity_id' => 1,
    ]]);
    $fixtures['emails'][] = $regA['email'];
    $fixtures['emails'][] = $regA['company']['email'];
    $coA = company($regA['company']['email']);
    check('company A registered', $coA !== null, $a->location);
    if ($coA === null) {
        throw new RuntimeException('Cannot continue without company A');
    }
    $fixtures['companies'][] = (int)$coA['entity_id'];
    $ownerA = (int)$coA['super_user_id'];
    $fixtures['customers'][] = $ownerA;
    check('status stays pending (posted status ignored)', $coA['status'] === '0', $coA['status']);
    check('erp_code not settable from storefront', $coA['company_id'] === null, (string)$coA['company_id']);
    check('super user is the new customer, not ID 1', $ownerA !== 1);
    check('website is the current one, not 99', (int)$coA['website_id'] === 1);

    // ------------------------------------------------------------------
    section('Signed status link (pending)');
    $mail = waitMail($regA['email'], '');
    check('applicant receives an email', $mail !== null);
    $mailsToCompany = mails($regA['company']['email']);
    check('company email gets no copy', count($mailsToCompany) === 0, (string)count($mailsToCompany));
    $statusToken = tokenFrom((string)($mail['HTML'] ?? ''), 'company/application/status');
    check('email carries a status link', $statusToken !== '');
    check('company name escaped in email', !str_contains((string)$mail['HTML'], '<b>a</b>'));

    $html = $anon->get('company/application/status/?token=' . $statusToken);
    check('status page 200', $anon->code === 200, (string)$anon->code);
    check('status page shows company', str_contains($html, "E2E &lt;b&gt;a&lt;/b&gt; $u"));
    check('company name escaped on page', !str_contains($html, '<b>a</b>'));
    check('Referrer-Policy no-referrer', ($anon->headers['referrer-policy'] ?? '') === 'no-referrer');
    check('page is noindex', (bool)preg_match('/name="robots" content="NOINDEX/i', $html));

    $tampered = substr($statusToken, 0, -1) . (substr($statusToken, -1) === 'a' ? 'b' : 'a');
    $html = $anon->get('company/application/status/?token=' . $tampered);
    check('tampered token rejected', str_contains($html, 'This link is not valid or has expired'));
    check('tampered token leaks no data', !str_contains($html, "E2E &lt;b&gt;a&lt;/b&gt;"));
    $forged = preg_replace('/^\d+\./', ($coA['entity_id'] + 1) . '.', $statusToken);
    $html = $anon->get('company/application/status/?token=' . $forged);
    check('token for another company ID rejected', str_contains($html, 'This link is not valid or has expired'));
    $html = $anon->get('company/application/status/?token=' . rawurlencode("1.1.' OR 1=1 --"));
    check('garbage token rejected', str_contains($html, 'This link is not valid or has expired'));

    // reply: CSRF + XSS
    $before = (int)$db->fetchOne('SELECT COUNT(*) FROM company_message WHERE company_entity_id = ?', [$coA['entity_id']]);
    $anon->post('company/application/reply/', ['token' => $statusToken, 'body' => 'no form key']);
    $after = (int)$db->fetchOne('SELECT COUNT(*) FROM company_message WHERE company_entity_id = ?', [$coA['entity_id']]);
    check('reply without form_key rejected', $after === $before);
    $key = $anon->formKey('company/application/status/?token=' . $statusToken);
    $xss = '<script>alert("e2e")</script><img src=x onerror=alert(1)> hola';
    $anon->post('company/application/reply/', ['form_key' => $key, 'token' => $statusToken, 'body' => $xss]);
    $after = (int)$db->fetchOne('SELECT COUNT(*) FROM company_message WHERE company_entity_id = ?', [$coA['entity_id']]);
    check('reply with form_key stored', $after === $before + 1);
    $html = $anon->get('company/application/status/?token=' . $statusToken);
    check('reply body escaped on page', str_contains($html, '&lt;script&gt;alert') && !str_contains($html, '<script>alert("e2e")'));
    check('reply with tampered token rejected', (function () use ($anon, $tampered, $key, $db, $coA, $after) {
        $anon->post('company/application/reply/', ['form_key' => $key, 'token' => $tampered, 'body' => 'x']);
        return (int)$db->fetchOne('SELECT COUNT(*) FROM company_message WHERE company_entity_id = ?', [$coA['entity_id']]) === $after;
    })());

    // admin message + internal note
    $api = new Http();
    $res = $api->json('POST', "rest/V1/companies/{$coA['entity_id']}/messages", [
        'body' => 'Admin says <i>hi</i> & <script>x()</script>', 'isInternal' => false,
    ], $adminToken);
    check('admin message via REST', isset($res['message_id']) || isset($res['id']), json_encode($res));
    $api->json('POST', "rest/V1/companies/{$coA['entity_id']}/messages", [
        'body' => 'INTERNAL-NOTE-' . $u, 'isInternal' => true,
    ], $adminToken);
    runConsumer();
    $mail = waitMail($regA['email'], 'New message');
    check('applicant notified of admin message', $mail !== null);
    if ($mail) {
        check('message email escapes body', !str_contains((string)$mail['HTML'], '<script>x()</script>'));
        check('message email carries status link', tokenFrom((string)$mail['HTML'], 'company/application/status') !== '');
    }
    $html = $anon->get('company/application/status/?token=' . $statusToken);
    check('admin message shown', str_contains($html, 'Admin says &lt;i&gt;hi&lt;/i&gt;'));
    check('internal note hidden', !str_contains($html, 'INTERNAL-NOTE-' . $u));

    // ------------------------------------------------------------------
    section('Reject, resubmit, approve');
    $res = $api->json('POST', "rest/V1/companies/{$coA['entity_id']}/reject", ['reason' => "Missing <b>docs</b> $u"], $adminToken);
    check('reject via REST', (int)($res['status'] ?? -1) === 2, json_encode($res));
    $mail = waitMail($regA['email'], 'Your company application');
    check('rejection email', $mail !== null);
    $rejectToken = $mail ? tokenFrom((string)$mail['HTML'], 'company/application/status') : '';
    check('rejection email link is storefront', $mail && !str_contains((string)$mail['HTML'], '/admin'));
    $html = $anon->get('company/application/status/?token=' . $rejectToken);
    check('reason shown escaped', str_contains($html, "Missing &lt;b&gt;docs&lt;/b&gt; $u"));
    $key = $anon->formKey('company/application/status/?token=' . $rejectToken);
    $anon->post('company/application/resubmit/', ['token' => $rejectToken, 'comment' => 'no key']);
    check('resubmit without form_key rejected', company($regA['company']['email'])['status'] === '2');
    $anon->post('company/application/resubmit/', ['form_key' => $key, 'token' => $rejectToken, 'comment' => 'Docs attached']);
    check('resubmit -> pending', company($regA['company']['email'])['status'] === '0');
    $actor = $db->fetchOne(
        'SELECT CONCAT(actor_type, ":", actor_id) FROM company_status_history WHERE company_entity_id = ? ORDER BY history_id DESC LIMIT 1',
        [$coA['entity_id']]
    );
    check('resubmit recorded as the applicant', $actor === "customer:$ownerA", (string)$actor);

    $res = $api->json('POST', "rest/V1/companies/{$coA['entity_id']}/approve", ['comment' => 'Welcome'], $adminToken);
    check('approve via REST', (int)($res['status'] ?? -1) === 1, json_encode($res));
    $anon->get('company/application/status/?token=' . $statusToken);
    check('link of approved company -> login', $anon->code === 302 && str_contains($anon->location, 'customer/account/login'), $anon->location);

    // ------------------------------------------------------------------
    section('My Company (customer A)');
    login($a, $regA['email']);
    $html = $a->get('company/account/');
    check('My Company page 200', $a->code === 200 && str_contains($html, "E2E &lt;b&gt;a&lt;/b&gt; $u"), (string)$a->code);
    $key = $a->formKey('company/account/edit/');
    $a->post('company/account/editPost/', [
        'form_key' => $key,
        'company' => array_merge($regA['company'], [
            'name' => "E2E A edited $u", 'status' => 3, 'company_id' => 'HACK2', 'erp_code' => 'HACK2', 'super_user_id' => 1,
        ]),
    ]);
    $row = company($regA['company']['email']);
    check('edit saved', $row['name'] === "E2E A edited $u", $row['name']);
    check('edit cannot change status/erp/super user', $row['status'] === '1' && $row['company_id'] === null
        && (int)$row['super_user_id'] === $ownerA);
    $a->post('company/account/editPost/', ['company' => ['name' => 'csrf-name']]);
    check('edit without form_key rejected', company($regA['company']['email'])['name'] === "E2E A edited $u");

    $key = $a->formKey('company/address/edit/');
    $a->post('company/address/formPost/', [
        'form_key' => $key,
        'address' => [
            'label' => "E2E addr $u", 'firstname' => 'Ana', 'lastname' => 'Tester', 'street' => ['Gran Via 2'],
            'city' => 'Madrid', 'country_id' => 'ES', 'region' => 'Madrid', 'postcode' => '28013',
            'telephone' => '+34 600 000 002', 'parent_id' => 1,
        ],
    ]);
    $addrA = (int)$db->fetchOne(
        'SELECT MAX(entity_id) FROM company_address_entity WHERE parent_id = ?',
        [$coA['entity_id']]
    );
    $addrCount = (int)$db->fetchOne('SELECT COUNT(*) FROM company_address_entity WHERE parent_id = ?', [$coA['entity_id']]);
    check('address added to own company (parent_id ignored)', $addrCount === 2, (string)$addrCount);

    // invitation
    $invitee = "e2e-$u-invitee@example.com";
    $fixtures['emails'][] = $invitee;
    $key = $a->formKey('company/users/');
    $a->post('company/users/invite/', ['form_key' => $key, 'email' => $invitee, 'role' => 'admin', 'job_title' => 'Buyer']);
    $mail = waitMail($invitee, '');
    check('invitation email', $mail !== null);
    $invToken = $mail ? tokenFrom((string)$mail['HTML'], 'company/invitation/accept') : '';
    check('invitation link', $invToken !== '');
    $inv = new Http();
    $key = $inv->formKey('company/invitation/accept/?token=' . $invToken);
    $inv->post('company/invitation/acceptPost/', [
        'form_key' => $key, 'token' => $invToken, 'firstname' => 'Ivo', 'lastname' => 'Invited',
        'password' => PASS, 'password_confirmation' => PASS,
    ]);
    $member = $db->fetchRow(
        'SELECT cc.* FROM company_customer cc JOIN customer_entity ce ON ce.entity_id = cc.customer_id WHERE ce.email = ?',
        [$invitee]
    );
    if ($member) {
        $fixtures['customers'][] = (int)$member['customer_id'];
    }
    check('invitee joined company A', $member && (int)$member['company_entity_id'] === (int)$coA['entity_id']);
    check('invitee role as invited', $member && $member['role'] === 'admin', (string)($member['role'] ?? ''));
    $inv->post('company/invitation/acceptPost/', [
        'form_key' => $key, 'token' => $invToken, 'firstname' => 'X', 'lastname' => 'Y',
        'password' => PASS, 'password_confirmation' => PASS,
    ]);
    $uses = (int)$db->fetchOne(
        'SELECT COUNT(*) FROM company_customer cc JOIN customer_entity ce ON ce.entity_id = cc.customer_id WHERE ce.email LIKE ?',
        ["e2e-$u-invitee%"]
    );
    check('invitation token single use', $uses === 1);

    // ------------------------------------------------------------------
    section('Cross-company access (customer B)');
    $regB = register($b, $u, 'b');
    $fixtures['emails'][] = $regB['email'];
    $fixtures['emails'][] = $regB['company']['email'];
    $coB = company($regB['company']['email']);
    check('company B registered', $coB !== null);
    $fixtures['companies'][] = (int)$coB['entity_id'];
    $fixtures['customers'][] = (int)$coB['super_user_id'];
    $api->json('POST', "rest/V1/companies/{$coB['entity_id']}/approve", ['comment' => 'ok'], $adminToken);
    login($b, $regB['email']);

    $html = $b->get("company/address/edit/id/$addrA/");
    check('B cannot open A address', !str_contains($html, 'Gran Via 2'));
    $key = $b->formKey('company/address/');
    $b->post('company/address/delete/', ['form_key' => $key, 'id' => $addrA]);
    check('B cannot delete A address', (bool)$db->fetchOne('SELECT 1 FROM company_address_entity WHERE entity_id = ?', [$addrA]));
    $b->post('company/address/formPost/', ['form_key' => $key, 'id' => $addrA, 'address' => ['city' => 'Hacked', 'firstname' => 'x', 'lastname' => 'y', 'street' => ['z'], 'country_id' => 'ES', 'telephone' => '1']]);
    check('B cannot edit A address', !$db->fetchOne(
        'SELECT 1 FROM company_address_entity_varchar v JOIN eav_attribute ea ON ea.attribute_id = v.attribute_id WHERE v.entity_id = ? AND ea.attribute_code = "city" AND v.value = "Hacked"',
        [$addrA]
    ) && !$db->fetchOne('SELECT 1 FROM company_address_entity WHERE entity_id = ? AND city = "Hacked"', [$addrA]));
    $b->post('company/address/setDefault/', ['form_key' => $key, 'id' => $addrA, 'type' => 'billing']);
    check('B cannot set A address as default', (int)$db->fetchOne(
        'SELECT COALESCE(default_billing, 0) FROM company_entity WHERE entity_id = ?',
        [$coB['entity_id']]
    ) !== $addrA);
    $key = $b->formKey('company/users/');
    $b->post('company/users/remove/', ['form_key' => $key, 'id' => $ownerA]);
    $b->post('company/users/save/', ['form_key' => $key, 'id' => (int)$member['customer_id'], 'role' => 'user', 'status' => 0]);
    check('B cannot remove A users', (bool)$db->fetchOne('SELECT 1 FROM company_customer WHERE customer_id = ?', [$ownerA]));
    check('B cannot edit A users', $db->fetchOne('SELECT role FROM company_customer WHERE customer_id = ?', [(int)$member['customer_id']]) === 'admin');
    $html = $b->get('company/message/');
    check('B thread does not show A messages', !str_contains($html, 'Admin says'));

    // ------------------------------------------------------------------
    section('REST / GraphQL as customers');
    $tokA = trim($api->request('POST', 'rest/V1/integration/customer/token', json_encode(['username' => $regA['email'], 'password' => PASS]), ['Content-Type: application/json']), '"');
    $tokB = trim($api->request('POST', 'rest/V1/integration/customer/token', json_encode(['username' => $regB['email'], 'password' => PASS]), ['Content-Type: application/json']), '"');
    check('customer tokens issued', strlen($tokA) > 20 && strlen($tokB) > 20);
    $mine = $api->json('GET', 'rest/V1/companies/mine', null, $tokA);
    check('REST mine returns own company', (int)($mine['entity_id'] ?? $mine['id'] ?? 0) === (int)$coA['entity_id'], json_encode($mine));
    $res = $api->json('PUT', 'rest/V1/companies/mine', ['company' => [
        'name' => "E2E A rest $u", 'email' => $regA['company']['email'], 'status' => 3, 'company_id' => 'HACK3',
        'super_user_id' => 1, 'website_id' => 99,
        'custom_attributes' => [
            ['attribute_code' => 'legal_name', 'value' => "E2E Legal $u"], ['attribute_code' => 'vat_id', 'value' => "VAT{$u}a"],
            ['attribute_code' => 'phone', 'value' => '+34 600 000 000'],
        ],
    ]], $tokA);
    $row = company($regA['company']['email']);
    echo '       (PUT response: ' . substr(json_encode($res), 0, 160) . ")\n";
    check('REST mine PUT saved allowed fields', $row['name'] === "E2E A rest $u", $row['name']);
    check('REST mine PUT cannot change status/erp/super user/website', $row['status'] === '1'
        && $row['company_id'] === null && (int)$row['super_user_id'] === $ownerA && (int)$row['website_id'] === 1, json_encode($res));
    $res = $api->json('GET', "rest/V1/companies/{$coA['entity_id']}", null, $tokB);
    check('customer token cannot use admin REST', !isset($res['entity_id']) && !isset($res['name']));
    $res = $api->json('GET', "rest/V1/companies/{$coA['entity_id']}", null, null);
    check('anonymous cannot use admin REST', !isset($res['name']));
    $res = $api->json('GET', "rest/V1/companies/mine/addresses/$addrA", null, $tokB);
    check('REST B cannot read A address', !isset($res['city']), json_encode($res));
    $res = $api->json('DELETE', "rest/V1/companies/mine/addresses/$addrA", null, $tokB);
    check('REST B cannot delete A address', (bool)$db->fetchOne('SELECT 1 FROM company_address_entity WHERE entity_id = ?', [$addrA]));

    $gql = fn(string $q, ?string $t) => $api->json('POST', 'graphql', ['query' => $q], $t);
    $res = $gql('{ company { name status addresses { uid city } } }', $tokA);
    check('GraphQL company for A', str_starts_with((string)($res['data']['company']['name'] ?? ''), 'E2E A '), json_encode($res));
    check('GraphQL A sees only own addresses', count($res['data']['company']['addresses'] ?? []) === 2);
    $res = (new Http())->json('POST', 'graphql', ['query' => '{ company { name } }']);
    check('GraphQL company anonymous -> null/error', empty($res['data']['company']['name']), json_encode($res));
    $uid = base64_encode((string)$addrA);
    $res = $gql("mutation { deleteCompanyAddress(uid: \"$uid\") }", $tokB);
    check('GraphQL B cannot delete A address', !empty($res['errors'])
        && (bool)$db->fetchOne('SELECT 1 FROM company_address_entity WHERE entity_id = ?', [$addrA]));
    $res = $gql('{ company { name } }', 'garbage-token');
    check('GraphQL invalid token rejected', !empty($res['errors']));

    // ------------------------------------------------------------------
    section('Admin grids: SQL injection probes (in-process)');
    $probe = function (string $class, array $args, callable $act) use ($om): string {
        $provider = $om->create($class, $args + ['name' => 'e2e', 'primaryFieldName' => 'entity_id', 'requestFieldName' => 'id']);
        $act($provider);
        $start = microtime(true);
        try {
            $provider->getData();
        } catch (\Throwable $e) {
            return 'exception: ' . get_class($e) . ' ' . substr($e->getMessage(), 0, 120);
        }
        $elapsed = microtime(true) - $start;
        $sql = (string)$provider->getCollection()->getSelect();

        return ($elapsed > 2 ? 'slow ' : '') . (stripos($sql, 'sleep') !== false || stripos($sql, 'password_hash') !== false ? 'injected' : 'clean');
    };
    $sortField = 'entity_id, (SELECT SLEEP(3))';
    $sortDir = 'ASC, (SELECT SLEEP(3))';
    $filter = fn(string $f, string $v, string $c) => $om->create(\Magento\Framework\Api\Filter::class)
        ->setField($f)->setValue($v)->setConditionType($c);
    $r = $probe(\MageOS\Company\Ui\DataProvider\Company\ListingDataProvider::class, [], fn($p) => $p->addOrder($sortField, 'ASC'));
    check('company grid: injected sort field', $r === 'clean', $r);
    $r = $probe(\MageOS\Company\Ui\DataProvider\Company\ListingDataProvider::class, [], fn($p) => $p->addOrder('name', $sortDir));
    check('company grid: injected sort direction', $r === 'clean', $r);
    $r = $probe(
        \MageOS\Company\Ui\DataProvider\Company\ListingDataProvider::class,
        [],
        fn($p) => $p->addFilter($filter('su.password_hash', '%a%', 'like'))
    );
    check('company grid: filter on joined column ignored', $r === 'clean', $r);
    $r = $probe(
        \MageOS\Company\Ui\DataProvider\Company\ListingDataProvider::class,
        [],
        fn($p) => $p->addFilter($filter('fulltext', "x%' OR SLEEP(3) OR '", 'fulltext'))
    );
    check('company grid: fulltext quoted', !str_starts_with($r, 'slow') && !str_starts_with($r, 'exception'), $r);
    $r = $probe(\MageOS\Company\Ui\DataProvider\Attribute\ListingDataProvider::class, ['primaryFieldName' => 'attribute_id'], fn($p) => $p->addOrder('attribute_code', $sortDir));
    check('attribute grid: injected sort direction', $r === 'clean', $r);
    $r = $probe(\MageOS\Company\Ui\DataProvider\Message\ThreadListingDataProvider::class, [], fn($p) => $p->addOrder('name', $sortDir));
    check('message grid: injected sort direction', $r === 'clean', $r);
    $r = $probe(\MageOS\Company\Ui\DataProvider\Message\ThreadListingDataProvider::class, [], fn($p) => $p->addOrder('unread_count', $sortDir));
    check('message grid: injected aggregate sort direction', $r === 'clean', $r);
    $req = $om->get(\Magento\Framework\App\RequestInterface::class);
    $req->setParam('company_id', $coA['entity_id']);
    $r = $probe(\MageOS\Company\Ui\DataProvider\Address\ListingDataProvider::class, [], fn($p) => $p->addOrder('city', $sortDir));
    check('address grid: injected sort direction', $r === 'clean', $r);
} catch (\Throwable $e) {
    $fail++;
    echo "  FATAL " . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    section('Cleanup');
    $registry = $om->get(\Magento\Framework\Registry::class);
    $registry->register('isSecureArea', true, true);
    $companyRepo = $om->get(\MageOS\Company\Api\CompanyRepositoryInterface::class);
    foreach (array_unique($fixtures['companies']) as $id) {
        try {
            $companyRepo->deleteById($id);
        } catch (\Throwable $e) {
            echo "  company $id: " . $e->getMessage() . "\n";
        }
    }
    $customerRepo = $om->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
    foreach (array_unique($fixtures['customers']) as $id) {
        try {
            $customerRepo->deleteById($id);
        } catch (\Throwable $e) {
            echo "  customer $id: " . $e->getMessage() . "\n";
        }
    }
    $db->delete('customer_entity', ['email LIKE ?' => "e2e-$u-%"]);
    $db->delete('email_log', ['recipient_email LIKE ?' => 'e2e-%@example.com%']);
    $db->delete('adminnotification_inbox', ['title LIKE ?' => "%E2E%$u%"]);
    $ids = [];
    foreach (array_unique($fixtures['emails']) as $email) {
        foreach (mails($email) as $m) {
            $ids[] = $m['ID'];
        }
    }
    foreach (mails('example.com') as $m) {
        if (str_contains(json_encode($m), "e2e-$u")) {
            $ids[] = $m['ID'];
        }
    }
    if ($ids) {
        $ctx = stream_context_create(['http' => [
            'method' => 'DELETE', 'header' => 'Content-Type: application/json',
            'content' => json_encode(['IDs' => array_values(array_unique($ids))]),
        ]]);
        file_get_contents(MAIL . 'messages', false, $ctx);
    }
    $om->get(\Magento\Integration\Api\UserTokenRevokerInterface::class)->revokeFor($adminContext);
    $left = (int)$db->fetchOne('SELECT COUNT(*) FROM company_entity WHERE email LIKE ?', ["e2e-$u-%"])
        + (int)$db->fetchOne('SELECT COUNT(*) FROM customer_entity WHERE email LIKE ?', ["e2e-$u-%"]);
    echo "  fixtures left: $left, mails deleted: " . count(array_unique($ids)) . "\n";
    echo "\nRESULT: $pass passed, $fail failed\n";
}
exit($fail ? 1 : 0);
