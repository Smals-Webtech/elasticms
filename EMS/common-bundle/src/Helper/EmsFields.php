<?php

namespace EMS\CommonBundle\Helper;

final class EmsFields
{
    public const CONTENT_MIME_TYPE_FIELD = 'mimetype';
    public const CONTENT_FILE_HASH_FIELD = 'sha1';
    public const CONTENT_FILE_SIZE_FIELD = 'filesize';
    public const CONTENT_FILE_NAME_FIELD = 'filename';
    public const CONTENT_MIME_TYPE_FIELD_ = '_mime_type';
    public const CONTENT_FILE_HASH_FIELD_ = '_hash';
    public const CONTENT_FILE_ALGO_FIELD_ = '_algo';
    public const CONTENT_FILE_SIZE_FIELD_ = '_file_size';
    public const CONTENT_FILE_NAME_FIELD_ = '_filename';
    public const CONTENT_FILE_NAMES = '_file_names';
    public const CONTENT_FILE_CONTENT = '_content';
    public const CONTENT_FILE_LANGUAGE = '_language';
    public const CONTENT_FILE_DATE = '_date';
    public const CONTENT_FILE_AUTHOR = '_author';
    public const CONTENT_FILE_TITLE = '_title';
    public const CONTENT_HASH_ALGO_FIELD = '_hash_algo';
    public const CONTENT_PUBLISHED_DATETIME_FIELD = '_published_datetime';
    public const CONTENT_FILES = '_files';

    public const ASSET_CONFIG_DISPOSITION = '_disposition';
    public const ASSET_CONFIG_BACKGROUND = '_background';
    public const ASSET_CONFIG_TYPE = '_config_type';
    public const ASSET_CONFIG_URL_TYPE = '_url_type';
    public const ASSET_CONFIG_TYPE_IMAGE = 'image';
    public const ASSET_CONFIG_TYPE_ZIP = 'zip';
    public const ASSET_CONFIG_GRAVITY = '_gravity';
    public const ASSET_CONFIG_MIME_TYPE = '_mime_type';
    public const ASSET_CONFIG_FILE_NAMES = '_file_names';
    public const ASSET_CONFIG_HEIGHT = '_height';
    public const ASSET_CONFIG_QUALITY = '_quality';
    public const ASSET_CONFIG_IMAGE_FORMAT = '_image_format';
    public const ASSET_CONFIG_WEBP_IMAGE_FORMAT = 'webp';
    public const ASSET_CONFIG_GIF_IMAGE_FORMAT = 'gif';
    public const ASSET_CONFIG_BMP_IMAGE_FORMAT = 'bmp';
    public const ASSET_CONFIG_JPEG_IMAGE_FORMAT = 'jpeg';
    public const ASSET_CONFIG_PNG_IMAGE_FORMAT = 'png';
    public const ASSET_CONFIG_RESIZE = '_resize';
    public const ASSET_CONFIG_WIDTH = '_width';
    public const ASSET_CONFIG_RADIUS = '_radius';
    public const ASSET_CONFIG_RADIUS_GEOMETRY = '_radius_geometry';
    public const ASSET_CONFIG_BORDER_COLOR = '_border_color';
    public const ASSET_CONFIG_WATERMARK_HASH = '_watermark_hash';
    public const ASSET_CONFIG_GET_FILE_PATH = '_get_file_path';
    public const ASSET_CONFIG_ROTATE = '_rotate';
    public const ASSET_CONFIG_AUTO_ROTATE = '_auto_rotate';
    public const ASSET_CONFIG_FLIP_HORIZONTAL = '_flip_horizontal';
    public const ASSET_CONFIG_FLIP_VERTICAL = '_flip_vertical';
    public const ASSET_CONFIG_USERNAME = '_username';
    public const ASSET_CONFIG_PASSWORD = '_password';
    public const ASSET_CONFIG_AFTER = '_after';
    public const ASSET_CONFIG_BEFORE = '_before';
    public const ASSET_SEED = '_seed';

    public const LOG_ALIAS = 'ems_internal_logger_alias';
    public const LOG_TYPE = 'doc';
    public const LOG_ENVIRONMENT_FIELD = 'environment';
    public const LOG_CONTENTTYPE_FIELD = 'contenttype';
    public const LOG_OPERATION_FIELD = 'operation';
    public const LOG_USERNAME_FIELD = 'username';
    public const LOG_IMPERSONATOR_FIELD = 'impersonator';
    public const LOG_OUUID_FIELD = 'ouuid';
    public const LOG_REVISION_ID_FIELD = 'revision_id';
    public const LOG_KEY_FIELD = 'key';
    public const LOG_VALUE_FIELD = 'value';
    public const LOG_HOST_FIELD = 'host';
    public const LOG_URL_FIELD = 'url';
    public const LOG_ROUTE_FIELD = 'route';
    public const LOG_STATUS_CODE_FIELD = 'status_code';
    public const LOG_SIZE_FIELD = 'size';
    public const LOG_MICROTIME_FIELD = 'microtime';
    public const LOG_ERROR_MESSAGE_FIELD = 'error_message';
    public const LOG_EXCEPTION_FIELD = 'exception';
    public const LOG_SESSION_ID_FIELD = 'session_id';
    public const LOG_INSTANCE_ID_FIELD = 'instance_id';
    public const LOG_VERSION_FIELD = 'version';
    public const LOG_COMPONENT_FIELD = 'component';
    public const LOG_CONTEXT_FIELD = 'context';
    public const LOG_LEVEL_FIELD = 'level';
    public const LOG_MESSAGE_FIELD = 'message';
    public const LOG_LEVEL_NAME_FIELD = 'level_name';
    public const LOG_CHANNEL_FIELD = 'channel';
    public const LOG_DATETIME_FIELD = 'datetime';
    public const LOG_FIELD_IN_ERROR_FIELD = 'field';
    public const LOG_PATH_IN_ERROR_FIELD = 'path';
    public const LOG_EXIT_CODE = 'exit_code';
    public const LOG_COMMAND_NAME = 'command_name';
    public const LOG_COMMAND_LINE = 'command_line';

    public const LOG_OPERATION_CREATE = 'CREATE';
    public const LOG_OPERATION_UPDATE = 'UPDATE';
    public const LOG_OPERATION_READ = 'READ';
    public const LOG_OPERATION_DELETE = 'DELETE';
}
