<?php

namespace App\Http\Controllers;

use App\Exports\ContactCsvExport;
use App\Exports\ContactCsvTemplate;
use App\Exports\SpeedDialExport;
use App\Exports\SpeedDialTemplate;
use App\Imports\SpeedDialImport;
use App\Http\Requests\StoreVContactRequest;
use App\Http\Requests\UpdateVContactRequest;
use App\Imports\ContactCsvImport;
use App\Models\Extensions;
use App\Models\Groups;
use App\Models\User;
use App\Models\VContact;
use App\Models\VContactAttachment;
use App\Services\ContactService;
use App\Services\ContactVisibilityService;
use App\Services\Contacts\ContactCallingCardService;
use App\Services\Contacts\ContactFromAccountService;
use App\Services\Contacts\ContactUserLinkService;
use App\Services\Contacts\ContactImportService;
use App\Services\Contacts\ContactVcardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class ContactsController extends Controller
{
    protected int $perPage = 50;

    public function __construct(
        private ContactService $contactService,
        private ContactVisibilityService $contactVisibilityService,
        private ContactImportService $contactImportService,
        private ContactVcardService $contactVcardService,
        private ContactCallingCardService $contactCallingCardService,
        private ContactFromAccountService $contactFromAccountService,
    ) {}

    public function index(Request $request)
    {
        if (! userCheckPermission('contact_view')) {
            return redirect('/');
        }

        $speedDialMode = $request->boolean('speed_dial');
        $openSyncModal = $request->boolean('sync');

        return Inertia::render('Contacts', [
            'speedDialMode' => $speedDialMode,
            'openSyncModal' => $openSyncModal,
            'visibility' => $this->visibilityProps(),
            'routes' => [
                'current_page' => $speedDialMode
                    ? route('contacts.index', ['speed_dial' => 1])
                    : route('contacts.index'),
                'data_route' => route('phonebook-contacts.data'),
                'store' => route('phonebook-contacts.store'),
                'destroy_template' => route('phonebook-contacts.destroy', ['v_contact' => ':uuid']),
                'item_options' => route('phonebook-contacts.item.options'),
                'select_all' => route('phonebook-contacts.select.all'),
                'bulk_delete' => route('phonebook-contacts.bulk.delete'),
                'import_csv' => route('phonebook-contacts.import.csv'),
                'import_vcard' => route('phonebook-contacts.import.vcard'),
                'export_csv' => route('phonebook-contacts.export.csv'),
                'export_vcard' => route('phonebook-contacts.export.vcard'),
                'download_csv_template' => route('phonebook-contacts.export.csv-template'),
                'import_speed_dial' => route('phonebook-contacts.import.speed-dial'),
                'export_speed_dial' => route('phonebook-contacts.export.speed-dial'),
                'download_speed_dial_template' => route('phonebook-contacts.export.speed-dial-template'),
                'speed_dial' => route('contacts.index', ['speed_dial' => 1]),
                'contacts' => route('contacts.index'),
                'sync_status' => route('phonebook-contacts.sync.status'),
                'sync_disconnect' => route('phonebook-contacts.sync.disconnect', ['provider' => ':provider']),
                'sync_run' => route('phonebook-contacts.sync.run', ['provider' => ':provider']),
                'sync_toggle' => route('phonebook-contacts.sync.toggle', ['provider' => ':provider']),
                'connect_google' => route('contacts.sync.google.connect'),
                'connect_microsoft' => route('contacts.sync.microsoft.connect'),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function store(StoreVContactRequest $request): JsonResponse
    {
        try {
            $contact = $this->contactService->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Contact created successfully.']],
                'contact_uuid' => $contact->contact_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('ContactsController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create contact.']],
            ], 500);
        }
    }

    public function storeFromExtension(Extensions $extension): JsonResponse
    {
        if (! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($extension->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $contact = $this->contactFromAccountService->createFromExtension($extension);

            return response()->json([
                'messages' => ['success' => ['Contact created and linked to this extension.']],
                'contact_uuid' => $contact->contact_uuid,
                'contact_label' => $this->contactLabel($contact),
            ], 201);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'messages' => ['error' => [$exception->getMessage()]],
            ], 422);
        } catch (\Throwable $e) {
            logger('ContactsController@storeFromExtension error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create contact from extension.']],
            ], 500);
        }
    }

    public function storeFromUser(User $user): JsonResponse
    {
        if (! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($user->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $contact = $this->contactFromAccountService->createFromUser($user);

            return response()->json([
                'messages' => ['success' => ['Contact created and linked to this user.']],
                'contact_uuid' => $contact->contact_uuid,
                'contact_label' => $this->contactLabel($contact),
            ], 201);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'messages' => ['error' => [$exception->getMessage()]],
            ], 422);
        } catch (\Throwable $e) {
            logger('ContactsController@storeFromUser error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create contact from user.']],
            ], 500);
        }
    }

    public function bulkStoreFromExtensions(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['uuid'],
        ]);

        $result = $this->contactFromAccountService->bulkCreateFromExtensions($validated['items']);

        return response()->json([
            'messages' => $this->bulkCreateMessages($result, 'extension'),
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'failed' => count($result['failed']),
        ]);
    }

    public function bulkStoreFromUsers(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['uuid'],
        ]);

        $result = $this->contactFromAccountService->bulkCreateFromUsers($validated['items']);

        return response()->json([
            'messages' => $this->bulkCreateMessages($result, 'user'),
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'failed' => count($result['failed']),
        ]);
    }

    public function update(UpdateVContactRequest $request, VContact $v_contact): JsonResponse
    {
        if (! $this->contactIsAccessible($v_contact)) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $this->contactService->save($request->validated(), $v_contact);

            return response()->json([
                'messages' => ['success' => ['Contact updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ContactsController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update contact.']],
            ], 500);
        }
    }

    public function destroy(VContact $v_contact): JsonResponse
    {
        if (! userCheckPermission('contact_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $this->contactIsAccessible($v_contact)) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $this->contactService->delete($v_contact);

            return response()->json([
                'messages' => ['success' => ['Contact deleted successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ContactsController@destroy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to delete contact.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('contact_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $itemQuery = VContact::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid);

            $this->contactVisibilityService->applyPortalListScope($itemQuery);

            $item = $itemQuery->with([
                    'phones',
                    'emails',
                    'addresses',
                    'notes',
                    'urls',
                    'times',
                    'relations.relatedContact',
                    'attachments',
                    'contactUsers.user' => function ($query) {
                        $query->select('user_uuid', 'username', 'domain_uuid');
                    },
                    'contactUsers.user.user_adv_fields:user_uuid,first_name,last_name',
                    'contactGroups.group:group_uuid,group_name',
                ])
                ->firstOrFail();
        } else {
            $item = new VContact();
            $item->contact_type = 'individual';
            $item->contact_time_zone = get_local_time_zone(session('domain_uuid'));
            $item->setRelation('phones', collect());
            $item->setRelation('emails', collect());
            $item->setRelation('addresses', collect());
            $item->setRelation('notes', collect());
            $item->setRelation('urls', collect());
            $item->setRelation('times', collect());
            $item->setRelation('relations', collect());
            $item->setRelation('attachments', collect());
            $item->setRelation('contactUsers', collect());
            $item->setRelation('contactGroups', collect());
        }

        $userOptions = $this->userOptions();
        $extensionOptions = $this->extensionOptions();
        $phonebookExtensionUuid = $itemUuid
            ? Extensions::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->where('phonebook_contact_uuid', $item->contact_uuid)
                ->value('extension_uuid')
            : null;

        $itemPayload = $itemUuid
            ? array_merge($item->toArray(), [
                'contact_users' => app(ContactUserLinkService::class)->formatContactUserAssignmentsForForm(
                    $item->contactUsers,
                    $userOptions,
                ),
                'phonebook_extension_uuid' => $phonebookExtensionUuid,
            ], $this->contactCallingCardService->formatForForm($item->contact_uuid))
            : array_merge($item->toArray(), $this->contactCallingCardService->formatForForm(null));

        return response()->json([
            'item' => $itemPayload,
            'visibility' => array_merge($this->visibilityProps(), [
                'linked_extensions' => $itemUuid
                    ? app(ContactUserLinkService::class)->formatLinkedExtensionsForContact($item)
                    : [],
            ]),
            'user_options' => $userOptions,
            'extension_options' => $extensionOptions,
            'group_options' => $this->groupOptions(),
            'contact_types' => $this->contactTypes(),
            'timezones' => getGroupedTimezones(),
            'phone_labels' => $this->phoneLabels(),
            'email_labels' => $this->emailLabels(),
            'address_labels' => $this->addressLabels(),
            'url_types' => $this->urlTypes(),
            'contact_options' => $this->contactOptions($itemUuid),
            'permissions' => $this->permissions(),
            'routes' => [
                'store_route' => route('phonebook-contacts.store'),
                'update_route' => $itemUuid
                    ? route('phonebook-contacts.update', ['v_contact' => $item->contact_uuid])
                    : null,
                'attachment_store' => $itemUuid && Route::has('phonebook-contacts.attachments.store')
                    ? route('phonebook-contacts.attachments.store', ['v_contact' => $item->contact_uuid])
                    : null,
                'attachment_destroy_template' => $itemUuid && Route::has('phonebook-contacts.attachments.destroy')
                    ? route('phonebook-contacts.attachments.destroy', ['v_contact' => $item->contact_uuid, 'attachment' => ':uuid'])
                    : null,
                'attachment_download_template' => $itemUuid && Route::has('phonebook-contacts.attachments.download')
                    ? route('phonebook-contacts.attachments.download', ['v_contact' => $item->contact_uuid, 'attachment' => ':uuid'])
                    : null,
            ],
        ]);
    }

    public function storeAttachment(Request $request, VContact $v_contact): JsonResponse
    {
        if (! userCheckPermission('contact_attachment_add') && ! userCheckPermission('contact_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($v_contact->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'attachment_description' => ['nullable', 'string', 'max:255'],
            'attachment_primary' => ['nullable'],
        ]);

        try {
            $file = $validated['file'];
            $attachment = VContactAttachment::query()->create([
                'contact_attachment_uuid' => (string) Str::uuid(),
                'domain_uuid' => session('domain_uuid'),
                'contact_uuid' => $v_contact->contact_uuid,
                'attachment_filename' => $file->getClientOriginalName(),
                'attachment_content' => base64_encode(file_get_contents($file->getRealPath())),
                'attachment_description' => $validated['attachment_description'] ?? null,
                'attachment_primary' => in_array($validated['attachment_primary'] ?? null, [1, '1', true, 'true'], true) ? 1 : null,
                'attachment_uploaded_date' => now(),
                'attachment_uploaded_user_uuid' => session('user_uuid'),
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);

            return response()->json([
                'messages' => ['success' => ['Attachment uploaded successfully.']],
                'attachment' => $attachment,
            ], 201);
        } catch (\Throwable $e) {
            logger('ContactsController@storeAttachment error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to upload attachment.']],
            ], 500);
        }
    }

    public function destroyAttachment(VContact $v_contact, VContactAttachment $attachment): JsonResponse
    {
        if (! userCheckPermission('contact_attachment_delete') && ! userCheckPermission('contact_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($v_contact->domain_uuid !== session('domain_uuid') || $attachment->contact_uuid !== $v_contact->contact_uuid) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $attachment->delete();

        return response()->json([
            'messages' => ['success' => ['Attachment deleted successfully.']],
        ]);
    }

    public function downloadAttachment(VContact $v_contact, VContactAttachment $attachment)
    {
        if (! userCheckPermission('contact_attachment_view') && ! userCheckPermission('contact_view')) {
            abort(403);
        }

        if ($v_contact->domain_uuid !== session('domain_uuid') || $attachment->contact_uuid !== $v_contact->contact_uuid) {
            abort(403);
        }

        $attachment = VContactAttachment::query()
            ->where('contact_attachment_uuid', $attachment->contact_attachment_uuid)
            ->where('contact_uuid', $v_contact->contact_uuid)
            ->where('domain_uuid', session('domain_uuid'))
            ->firstOrFail();

        $content = base64_decode((string) $attachment->attachment_content, true);

        if ($content === false) {
            abort(404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes($attachment->attachment_filename) . '"',
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('contact_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $query = $this->scopedContacts($request)
            ->select([
                'contact_uuid',
                'domain_uuid',
                'contact_type',
                'contact_organization',
                'contact_name_given',
                'contact_name_family',
                'contact_title',
                'contact_role',
                'contact_category',
                'contact_url',
                'contact_time_zone',
                'contact_note',
            ])
            ->with(['primaryPhone' => function ($query) {
                $query->select(
                    'contact_phone_uuid',
                    'contact_uuid',
                    'phone_label',
                    'phone_number',
                    'phone_extension',
                    'phone_speed_dial'
                );
            }])
            ->withExists('hasCallingCardSettings as has_calling_card');

        if ($request->boolean('filter.speedDial')) {
            $query->with(['contactUsers' => function ($query) {
                $query->select('contact_user_uuid', 'contact_uuid', 'user_uuid')
                    ->with(['user:user_uuid,username,domain_uuid']);
            }]);
        }

        return $query
            ->allowedSorts([
                'contact_organization',
                'contact_name_given',
                'contact_name_family',
                'contact_type',
                'contact_title',
                'contact_role',
                'contact_category',
            ])
            ->defaultSort('contact_organization')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedContacts($request)
            ->select(['contact_uuid'])
            ->defaultSort('contact_organization')
            ->pluck('contact_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching contacts selected.']],
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_upload') || ! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $import = new ContactCsvImport($this->contactImportService);
            $import->import($request->file('file'));

            if ($import->failures()->isNotEmpty()) {
                return response()->json([
                    'errors' => $this->formatImportFailures($import->failures()),
                ], 422);
            }

            return response()->json([
                'messages' => ['success' => ["Imported {$import->importedCount()} contact(s)."]],
            ]);
        } catch (Throwable $e) {
            logger('ContactsController@importCsv error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function importVcard(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_upload') || ! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $content = (string) file_get_contents($request->file('file')->getRealPath());
            $result = $this->contactImportService->importVcardContent($content);

            if ($result['imported'] === 0) {
                return response()->json([
                    'errors' => ['file' => ['No contacts were found in the vCard file.']],
                ], 422);
            }

            return response()->json([
                'messages' => ['success' => ["Imported {$result['imported']} contact(s)."]],
            ]);
        } catch (Throwable $e) {
            logger('ContactsController@importVcard error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function exportCsv(Request $request)
    {
        if (! userCheckPermission('contact_view')) {
            abort(403);
        }

        $contacts = $this->exportContacts($request);

        return Excel::download(new ContactCsvExport($contacts), 'phonebook-contacts.csv', ExcelWriter::CSV);
    }

    public function downloadCsvTemplate()
    {
        if (! userCheckPermission('contact_upload')) {
            abort(403);
        }

        return Excel::download(new ContactCsvTemplate, 'phonebook-contacts-template.csv', ExcelWriter::CSV);
    }

    public function importSpeedDialCsv(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_upload') || ! userCheckPermission('contact_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $import = new SpeedDialImport;
            $import->import($request->file('file'));

            if ($import->failures()->isNotEmpty()) {
                return response()->json([
                    'errors' => $this->formatImportFailures($import->failures()),
                ], 422);
            }

            return response()->json([
                'messages' => ['success' => ['Speed dial entries imported successfully.']],
            ]);
        } catch (Throwable $e) {
            logger('ContactsController@importSpeedDialCsv error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function exportSpeedDialCsv(Request $request)
    {
        if (! userCheckPermission('contact_view')) {
            abort(403);
        }

        $contacts = $this->exportContacts($request);

        return Excel::download(new SpeedDialExport($contacts), 'speed-dial.csv', ExcelWriter::CSV);
    }

    public function downloadSpeedDialTemplate()
    {
        if (! userCheckPermission('contact_upload')) {
            abort(403);
        }

        return Excel::download(new SpeedDialTemplate, 'speed-dial-template.csv', ExcelWriter::CSV);
    }

    public function exportVcard(Request $request)
    {
        if (! userCheckPermission('contact_view')) {
            abort(403);
        }

        $contacts = $this->exportContacts($request);
        $content = $this->contactVcardService->buildMany($contacts);

        return response($content, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="phonebook-contacts.vcf"',
        ]);
    }

    public function exportContactVcard(VContact $v_contact)
    {
        if (! userCheckPermission('contact_view')) {
            abort(403);
        }

        if (! $this->contactIsAccessible($v_contact)) {
            abort(403);
        }

        $v_contact->load(['phones', 'emails', 'addresses', 'urls', 'notes']);
        $filename = Str::slug($v_contact->display_name ?: 'contact') . '.vcf';

        return response($this->contactVcardService->build($v_contact), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (! userCheckPermission('contact_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No contacts selected.']],
            ], 422);
        }

        try {
            foreach ($items as $contact) {
                $this->contactService->delete($contact);
            }

            return response()->json([
                'messages' => ['success' => ["Deleted {$items->count()} contact(s)."]],
            ]);
        } catch (\Throwable $e) {
            logger('ContactsController@bulkDelete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to delete selected contacts.']],
            ], 500);
        }
    }

    private function exportContacts(Request $request): Collection
    {
        return $this->scopedContacts($request)
            ->with([
                'phones',
                'emails',
                'primaryPhone:contact_phone_uuid,contact_uuid,phone_number,phone_speed_dial',
                'contactUsers.user:user_uuid,username',
                'contactGroups.group:group_uuid,group_name',
            ])
            ->defaultSort('contact_organization')
            ->get();
    }

    private function formatImportFailures($failures): array
    {
        $errors = [];

        foreach ($failures as $failure) {
            foreach ($failure->errors() as $message) {
                $errors['row_' . ($failure->row() + 2)][] = $message;
            }
        }

        return $errors;
    }

    private function scopedContacts(Request $request): QueryBuilder
    {
        $query = VContact::query()
            ->when(! userCheckPermission('contact_all') || ! $request->boolean('filter.showGlobal'), function ($query) {
                $query->where('domain_uuid', session('domain_uuid'));
            });

        $this->contactVisibilityService->applyPortalListScope($query);

        return QueryBuilder::for($query)->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('contact_uuid', 'ilike', "%{$needle}%")
                            ->orWhere('contact_organization', 'ilike', "%{$needle}%")
                            ->orWhere('contact_name_given', 'ilike', "%{$needle}%")
                            ->orWhere('contact_name_family', 'ilike', "%{$needle}%")
                            ->orWhere('contact_title', 'ilike', "%{$needle}%")
                            ->orWhere('contact_role', 'ilike', "%{$needle}%")
                            ->orWhere('contact_category', 'ilike', "%{$needle}%")
                            ->orWhere('contact_note', 'ilike', "%{$needle}%")
                            ->orWhere('contact_url', 'ilike', "%{$needle}%")
                            ->orWhereHas('phones', function ($query) use ($needle) {
                                $query->where('phone_number', 'ilike', "%{$needle}%")
                                    ->orWhere('phone_label', 'ilike', "%{$needle}%")
                                    ->orWhere('phone_speed_dial', 'ilike', "%{$needle}%");
                            });
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
                AllowedFilter::callback('speedDial', function ($query, $value) {}),
            ]);
    }

    private function itemsFromRequest(Request $request): Collection
    {
        $uuids = collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();

        if (empty($uuids)) {
            return collect();
        }

        $query = VContact::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('contact_uuid', $uuids);

        $this->contactVisibilityService->applyPortalListScope($query);

        return $query->get();
    }

    private function contactOptions(?string $excludeUuid = null): array
    {
        return VContact::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->when($excludeUuid, fn ($query) => $query->where('contact_uuid', '!=', $excludeUuid))
            ->orderBy('contact_organization')
            ->orderBy('contact_name_given')
            ->get()
            ->map(fn (VContact $contact) => [
                'value' => $contact->contact_uuid,
                'label' => $contact->display_name,
            ])
            ->values()
            ->all();
    }

    private function contactTypes(): array
    {
        return [
            ['value' => 'individual', 'label' => 'Individual'],
            ['value' => 'user', 'label' => 'User'],
            ['value' => 'organization', 'label' => 'Organization'],
        ];
    }

    private function phoneLabels(): array
    {
        return $this->labelOptions(['work', 'home', 'mobile', 'main', 'billing', 'fax', 'voicemail', 'text', 'other']);
    }

    private function emailLabels(): array
    {
        return $this->labelOptions(['work', 'home', 'other']);
    }

    private function addressLabels(): array
    {
        return $this->labelOptions(['work', 'home', 'billing', 'other']);
    }

    private function urlTypes(): array
    {
        return $this->labelOptions(['work', 'home', 'other']);
    }

    private function labelOptions(array $values): array
    {
        return collect($values)
            ->map(fn (string $value) => ['value' => $value, 'label' => ucfirst($value)])
            ->all();
    }

    private function contactLabel(VContact $contact): string
    {
        $name = trim("{$contact->contact_name_given} {$contact->contact_name_family}");

        return $name !== '' ? $name : (trim((string) $contact->contact_organization) ?: $contact->contact_uuid);
    }

    /**
     * @param  array{created: int, skipped: int, failed: array<int, array{uuid: string, label: string, message: string}>}  $result
     * @return array<string, array<int, string>>
     */
    private function bulkCreateMessages(array $result, string $sourceLabel): array
    {
        $messages = [];

        if ($result['created'] > 0) {
            $messages['success'][] = "Created {$result['created']} contact(s) from {$sourceLabel}(s).";
        }

        if ($result['skipped'] > 0) {
            $messages['skipped'][] = "Skipped {$result['skipped']} {$sourceLabel}(s) that already have a contact or were not found.";
        }

        foreach ($result['failed'] as $index => $failure) {
            $messages['error_' . ($index + 1)][] = "{$failure['label']}: {$failure['message']}";
        }

        if ($result['created'] === 0 && $result['skipped'] === 0 && $result['failed'] === []) {
            $messages['error'][] = 'No contacts were created.';
        }

        if ($result['created'] === 0 && ! isset($messages['error']) && $result['failed'] === []) {
            $messages['error'][] = "No contacts were created from the selected {$sourceLabel}(s).";
        }

        return $messages;
    }

    private function contactIsAccessible(VContact $contact): bool
    {
        if ($contact->domain_uuid !== session('domain_uuid')) {
            return false;
        }

        $query = VContact::query()->whereKey($contact->contact_uuid);
        $this->contactVisibilityService->applyPortalListScope($query);

        return $query->exists();
    }

    private function extensionOptions(): array
    {
        return Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('extension')
            ->get([
                'extension_uuid',
                'extension',
                'directory_first_name',
                'directory_last_name',
                'effective_caller_id_name',
            ])
            ->map(function (Extensions $extension) {
                $label = trim((string) $extension->effective_caller_id_name);

                if ($label === '') {
                    $label = trim(trim((string) $extension->directory_first_name) . ' ' . trim((string) $extension->directory_last_name));
                }

                if ($label === '') {
                    $label = (string) $extension->extension;
                } else {
                    $label = "{$label} ({$extension->extension})";
                }

                return [
                    'value' => $extension->extension_uuid,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    private function visibilityProps(): array
    {
        return [
            'permissions_enabled' => $this->contactVisibilityService->contactPermissionsEnabled(),
            'provision_contact_users' => $this->contactVisibilityService->provisionContactUsersEnabled(),
            'provision_contact_groups' => $this->contactVisibilityService->provisionContactGroupsEnabled(),
            'provision_contact_extensions' => $this->contactVisibilityService->provisionContactExtensionsEnabled(),
            'directory_extension_count' => $this->contactVisibilityService->directoryExtensions()->count(),
        ];
    }

    private function userOptions(): array
    {
        return User::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('username')
            ->get(['user_uuid', 'username'])
            ->map(fn (User $user) => [
                'value' => $user->user_uuid,
                'label' => $user->name_formatted ?: $user->username,
            ])
            ->values()
            ->all();
    }

    private function groupOptions(): array
    {
        $domainUuid = session('domain_uuid');

        return Groups::query()
            ->where(function ($query) use ($domainUuid) {
                $query->whereNull('domain_uuid')
                    ->orWhere('domain_uuid', $domainUuid);
            })
            ->when(session('user.group_level') !== null, function ($query) {
                $query->where('group_level', '<=', session('user.group_level'));
            })
            ->orderBy('group_name')
            ->get(['group_uuid', 'group_name'])
            ->map(fn (Groups $group) => [
                'value' => $group->group_uuid,
                'label' => $group->group_name,
            ])
            ->values()
            ->all();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('contact_add'),
            'update' => userCheckPermission('contact_edit'),
            'destroy' => userCheckPermission('contact_delete'),
            'upload' => userCheckPermission('contact_upload'),
            'view_global' => userCheckPermission('contact_all'),
            'domain_view' => userCheckPermission('contact_domain_view'),
            'user_view' => userCheckPermission('contact_user_view'),
            'user_edit' => userCheckPermission('contact_user_add') || userCheckPermission('contact_user_edit') || userCheckPermission('contact_edit'),
            'group_view' => userCheckPermission('contact_group_view'),
            'group_edit' => userCheckPermission('contact_group_add') || userCheckPermission('contact_group_edit') || userCheckPermission('contact_edit'),
            'phone' => userCheckPermission('contact_phone_edit') || userCheckPermission('contact_edit'),
            'email' => userCheckPermission('contact_email_edit') || userCheckPermission('contact_edit'),
            'address' => userCheckPermission('contact_address_edit') || userCheckPermission('contact_edit'),
            'note' => userCheckPermission('contact_note_edit') || userCheckPermission('contact_edit'),
            'url' => userCheckPermission('contact_url_edit') || userCheckPermission('contact_edit'),
            'time' => userCheckPermission('contact_time_edit') || userCheckPermission('contact_edit'),
            'relation' => userCheckPermission('contact_relation_edit') || userCheckPermission('contact_edit'),
            'attachment_view' => userCheckPermission('contact_attachment_view') || userCheckPermission('contact_view'),
            'attachment_add' => userCheckPermission('contact_attachment_add') || userCheckPermission('contact_edit'),
            'attachment_delete' => userCheckPermission('contact_attachment_delete') || userCheckPermission('contact_edit'),
            'sync_connect' => userCheckPermission('contact_sync_connect'),
            'sync_run' => userCheckPermission('contact_sync_run'),
            'setting_view' => userCheckPermission('contact_setting_view') || userCheckPermission('contact_edit'),
            'setting_edit' => userCheckPermission('contact_setting_edit') || userCheckPermission('contact_edit'),
        ];
    }
}
