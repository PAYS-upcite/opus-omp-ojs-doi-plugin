<?php

/**
 * @defgroup plugins_generic_doisOpus
 */

/**
 * @file plugins/generic/doiOpus/index.php
 *
 * Copyright (c) 2022-2023 Bourrand Erwan
 * Distributed under the GNU GPL v3.
 *
 * @ingroup plugins_generic_doiOpus
 * @brief Wrapper for the DOI Opus Page plugin.
 *
 */
require_once('DoiOpus.inc.php');

return new DoiOpus();
