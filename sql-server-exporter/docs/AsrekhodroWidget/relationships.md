# AsrekhodroWidget — relationships

## Declared foreign keys (12)

| From | Column | To | Column | Constraint |
|------|--------|----|--------|------------|
| FileCategories | FileId | FileInitialize | Id | FK_FileCategories_FileInitialize |
| FilesFiletypes | FileTypeId | FileType | Id | FK_FilesFiletypes_FileType |
| WidgetCategories | ParentId | WidgetCategories | Id | FK_WidgetCategories_WidgetCategories |
| WidgetCategories | WidgetComponentId | WidgetComponents | Id | FK_WidgetCategories_WidgetComponents |
| WidgetCategoriesRolesRelation | WidgetCategoryId | WidgetCategories | Id | FK_WidgetCategoriesRolesRelation_WidgetCategories |
| WidgetCommonInfo | WidgetId | WidgetInitialize | Id | FK_WidgetCommonInfo_WidgetInitialize |
| WidgetFiles | WidgetId | WidgetInitialize | Id | FK_WidgetFiles_WidgetInitialize |
| WidgetInitialize | StatusId | WidgetStatus | Id | FK_WidgetInitialize_WidgetStatus |
| WidgetParameters | VariableTypeId | VariableType | Id | FK_WidgetParameters_VariableType |
| WidgetParameters | WidgetComponentId | WidgetComponents | Id | FK_WidgetParameters_WidgetComponents |
| WidgetParametersRelation | ParameterId | WidgetParameters | Id | FK_WidgetParametersRelation_WidgetParameters |
| WidgetPrivateInfo | WidgetId | WidgetInitialize | Id | FK_WidgetPrivateInfo_WidgetInitialize |

## Logical relationships

| From | Column | To | Column | Notes |
|------|--------|----|--------|-------|
| WidgetCommonInfo | WidgetId | WidgetInitialize | Id | Widget content |
| WidgetPrivateInfo | WidgetId | WidgetInitialize | Id | Widget workflow |
| WidgetCategoriesRelation | WidgetId | WidgetInitialize | Id | Widget categories |
| WidgetCategoriesRelation | CategoryId | WidgetCategories | Id | Category link |
| WidgetCategories | ParentId | WidgetCategories | Id | Category tree |
| WidgetFiles | WidgetId | WidgetInitialize | Id | Widget media |
| WidgetFiles | FileId | FileInitialize | Id | File link |
| FileCommonInfo | FileId | FileInitialize | Id | File metadata |
| WidgetParametersRelation | WidgetId | WidgetInitialize | Id | Widget parameters |
| FileInitialize | DomainId | Domain | Id | Cross-DB: domain in AsreKhodroBack |

## Cross-database links

| Direction | Link | Verified |
|-----------|------|----------|
| ← AsreKhodroBack | Domain.Id → FileInitialize.DomainId | DomainId=1 in Widget |

---

[← Back to AsrekhodroWidget overview](./README.md)
