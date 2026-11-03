<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Additive storage required by the existing native screens under issuer ownership. */
class Migration_Version_474 extends CI_Migration
{
    public function up(): void
    {
        // Each helper validates the persisted schema and is replay-safe. MySQL DDL
        // is not transactionally rolled back: a failure must leave tracking at 473
        // so the native migration runner can resume, never claim a partial success.
        foreach ([
            'NativeCompanyCustomFieldSchema',
            'NativeCompanyFilterSchema',
            'NativeCompanyEventSchema',
            'NativeCompanyHeaderSchema',
            'NativeCompanyAuxiliarySchema',
            'NativeCompanyMailDispatchFence','NativeCompanyFileMessageSchema',
            'CompanyCustomerGroupsSchema',
            'NativeCompanyWorkAssignmentsSchema',
            'NativeCompanyBlankTimerSchema',
            'CompanyInvoiceProjectSchema',
            'NativeCompanyContactImageSchema',
            'NativeCompanyWorkTagsSchema',
            'NativeCompanyConsentSchema','NativeCompanyKnowledgeSchema','CompanyCustomerAdminSchema','NativeCompanyPrivacySchema','NativeCompanyPaymentModesSchema','NativeCompanyProjectMetadataSchema','NativeCompanyProjectDeadlineSchema','NativeCompanyProjectFormStateSchema','CompanyContractsSchema','NativeCompanyTicketCustomerSchema','NativeCompanyDepartmentSchema','NativeCompanyTicketCatalogSchema','NativeCompanyCustomerFileSchema','NativeCompanyInvoiceFileSchema','NativeCompanyInvoiceDraftProjectionSchema','NativeCompanyInvoiceDraftLinesSchema','NativeCompanyInvoiceCommandSchema','NativePortalRememberSchema','NativePortalRegistrationSchema','NativeCompanyTaskFormSchema','NativeCompanyTicketReplySchema','NativeCompanyTicketPublicSchema','NativeCompanyEventMailSchema','NativeCompanyTaskChecklistSchema','NativeCompanySpamSchema','NativeCompanyStandaloneFollowersSchema','NativeCompanyTicketLifecycleSchema','NativeCompanyStaffInviteSchema','NativeCompanyStaffRateSchema','NativeCompanyTaskTimerDetailsSchema','NativeCompanyTicketMergeSchema','NativeCompanyPrivacyMailSchema','NativeCompanyEstimateRequestSchema','NativeCompanyExpenseBillabilitySchema','NativeCompanyExpenseFormSchema','NativeCompanyCustomerUploadMailSchema',
        ] as $schema) {
            require_once APPPATH . 'services/' . $schema . '.php';
            $schema::ensure($this->db, db_prefix());
        }
        require_once APPPATH.'services/CompanyInvoiceAttachmentSchema.php';
        CompanyInvoiceAttachmentSchema::ensureNativeCapacity($this->db, db_prefix());
        require_once APPPATH.'services/NativeCompanyInvoiceFileBackfill.php';
        NativeCompanyInvoiceFileBackfill::run($this->db, db_prefix());
        require_once APPPATH.'services/NativeCompanyInvoiceDraftProjection.php';
        (new NativeCompanyInvoiceDraftProjection($this->db, db_prefix()))->backfill();
        require_once APPPATH.'services/NativeCompanyInvoiceDraftLines.php';
        (new NativeCompanyInvoiceDraftLines($this->db, db_prefix()))->backfill();
        require_once APPPATH.'services/NativeCompanyTicketCatalogBackfill.php';
        NativeCompanyTicketCatalogBackfill::run($this->db, db_prefix());
        // The native installed rate column is the authority for decimal precision.
        // No module hooks or tenant settings execute during schema preparation.
        require_once APPPATH . 'services/NativeCompanyCatalogCurrencySchema.php';
        NativeCompanyCatalogCurrencySchema::ensure($this->db, db_prefix(), NativeCompanyCatalogCurrencySchema::installedPrecision($this->db, db_prefix()));
    }

    public function down(): void
    {
        // Never delete companies, operational data, lineage or dispatch receipts.
        throw new RuntimeException('native_company_schema_downgrade_requires_review');
    }
}
