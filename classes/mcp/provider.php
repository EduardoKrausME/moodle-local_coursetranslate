<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * MCP tool discovery for course translation.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * Discover optional translation tools without requiring local_mcp at installation.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /**
     * Register the available course translation read tools.
     *
     * @return array
     */
    public function get_read_tools(): array {
        return [new list_jobs(), new get_job(), new get_field()];
    }

    /**
     * Register the available confirmed translation write tools.
     *
     * @return array
     */
    public function get_write_tools(): array {
        return [new create_job(), new translate_items(), new apply_original(), new create_copy()];
    }
}
