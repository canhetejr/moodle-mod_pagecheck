// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Warns the student about a file before the upload finishes.
 *
 * This is a courtesy, not a gate. Every restriction is enforced again on the server when the
 * submission is saved and once more when it is sent for grading, so a student who disables
 * JavaScript, or a file this module cannot read, changes nothing about what is finally accepted.
 * When this module cannot be certain about a file, it says the server will check it rather than
 * showing a number that might be wrong.
 *
 * @module     mod_pagecheck/validator
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/templates', 'core/str', 'core/log'], function(Templates, Str, Log) {

    /**
     * Read the whole file when it is small, and only its ends when it is large.
     *
     * The page tree of a PDF lives near the start or near the end of the file, so the middle of a
     * large document is not worth loading into the browser's memory.
     */
    var MAX_FULL_READ = 31457280;

    /** How much of each end of a large file to read. */
    var EDGE_READ = 8388608;

    /** The activity restrictions, as handed over by edit.php. */
    var config = null;

    /** Where the messages are shown. */
    var REGION = 'pagecheck-client-issues';

    /**
     * Turn a byte count into something a person can read.
     *
     * @param {Number} bytes the size
     * @return {String}
     */
    var formatSize = function(bytes) {
        var units = ['bytes', 'KB', 'MB', 'GB'];
        var index = 0;
        var size = bytes;
        while (size >= 1024 && index < units.length - 1) {
            size = size / 1024;
            index++;
        }
        return (index === 0 ? size : size.toFixed(1)) + ' ' + units[index];
    };

    /**
     * Decode a chunk of bytes as latin1, which is how the structural parts of a PDF are written.
     *
     * @param {ArrayBuffer} buffer the bytes
     * @return {String}
     */
    var decode = function(buffer) {
        var decoder = new TextDecoder('latin1');
        return decoder.decode(new Uint8Array(buffer));
    };

    /**
     * Read the parts of a file this module needs.
     *
     * @param {File} file the file the student picked
     * @return {Promise} resolves with the decoded text
     */
    var readFile = function(file) {
        if (file.size <= MAX_FULL_READ) {
            return file.arrayBuffer().then(decode);
        }
        var head = file.slice(0, EDGE_READ).arrayBuffer();
        var tail = file.slice(file.size - EDGE_READ).arrayBuffer();
        return Promise.all([head, tail]).then(function(parts) {
            return decode(parts[0]) + decode(parts[1]);
        });
    };

    /**
     * Count the pages of a PDF from its raw text.
     *
     * Returns null whenever the answer cannot be trusted: an encrypted document, a document whose
     * objects sit inside compressed object streams, or one where neither the page tree nor the
     * page objects are visible in what was read.
     *
     * @param {String} text the decoded file content
     * @return {Number|null} the page count, or null when it is not certain
     */
    var countPdfPages = function(text) {
        if (/\/Encrypt\s+\d+\s+\d+\s+R/.test(text) || /\/Type\s*\/ObjStm/.test(text)) {
            return null;
        }

        // The root of the page tree carries the total in its /Count entry.
        var counts = [];
        var pagesNode = /\/Type\s*\/Pages\b/g;
        var match;
        while ((match = pagesNode.exec(text)) !== null) {
            // Look for the /Count of the same dictionary, which is within a few hundred bytes.
            var window = text.substring(Math.max(0, match.index - 400), match.index + 400);
            var count = /\/Count\s+(\d+)/.exec(window);
            if (count) {
                counts.push(parseInt(count[1], 10));
            }
        }
        if (counts.length) {
            return Math.max.apply(null, counts);
        }

        var leaves = text.match(/\/Type\s*\/Page(?![sX])/g);
        return leaves ? leaves.length : null;
    };

    /**
     * Work out everything that is wrong with one file.
     *
     * @param {File} file the file the student picked
     * @return {Promise} resolves with an array of {key, param} describing each problem
     */
    var inspect = function(file) {
        var name = file.name;
        var extension = name.indexOf('.') === -1 ? '' : name.split('.').pop().toLowerCase();
        var problems = [];

        if (config.allowedextensions.length && config.allowedextensions.indexOf(extension) === -1) {
            problems.push({
                key: 'issue_badextension',
                param: {extension: extension || '?', allowed: config.allowedextensions.join(', ')}
            });
            // A file of the wrong type is not worth opening.
            return Promise.resolve(problems);
        }

        if (config.maxbytes > 0 && file.size > config.maxbytes) {
            problems.push({
                key: 'issue_toolarge',
                param: {size: formatSize(file.size), max: formatSize(config.maxbytes)}
            });
        }

        if (extension !== 'pdf' || (config.minpages <= 0 && config.maxpages <= 0)) {
            // Only a PDF can be counted here. For anything else the server has the last word.
            return Promise.resolve(problems);
        }

        return readFile(file).then(function(text) {
            var pages = countPdfPages(text);
            if (pages === null) {
                problems.push({key: 'checkonserver', param: null});
                return problems;
            }
            var counted = Math.max(0, pages - config.countcover);
            if (config.minpages > 0 && counted < config.minpages) {
                problems.push({key: 'issue_toofewpages', param: {count: counted, min: config.minpages}});
            }
            if (config.maxpages > 0 && counted > config.maxpages) {
                problems.push({key: 'issue_toomanypages', param: {count: counted, max: config.maxpages}});
            }
            return problems;
        }).catch(function(error) {
            Log.debug('mod_pagecheck could not read the file in the browser: ' + error);
            problems.push({key: 'checkonserver', param: null});
            return problems;
        });
    };

    /**
     * Show the problems found with a file.
     *
     * @param {String} filename the file the problems belong to
     * @param {Array} problems what inspect() found
     * @return {Promise}
     */
    var render = function(filename, problems) {
        var region = document.getElementById(REGION);
        if (!region) {
            return Promise.resolve();
        }
        if (!problems.length) {
            region.innerHTML = '';
            return Promise.resolve();
        }

        var requests = problems.map(function(problem) {
            return {key: problem.key, component: 'mod_pagecheck', param: problem.param};
        });

        return Str.get_strings(requests).then(function(messages) {
            var wrapped = messages.map(function(message, index) {
                return {
                    key: problems[index].key,
                    message: filename + ': ' + message,
                    // "Warn only" never blocks, and an uncertain answer is never an error.
                    iserror: config.strictness === 'block' && problems[index].key !== 'checkonserver'
                };
            });
            return Templates.render('mod_pagecheck/issue_list', {
                issues: wrapped,
                checking: false
            });
        }).then(function(html) {
            region.innerHTML = html;
            return html;
        }).catch(function(error) {
            Log.debug('mod_pagecheck could not render its warnings: ' + error);
        });
    };

    /**
     * Show that a file is being looked at.
     *
     * @return {Promise}
     */
    var renderChecking = function() {
        var region = document.getElementById(REGION);
        if (!region) {
            return Promise.resolve();
        }
        return Templates.render('mod_pagecheck/issue_list', {issues: [], checking: true})
            .then(function(html) {
                region.innerHTML = html;
                return html;
            }).catch(function() {
                return null;
            });
    };

    /**
     * Handle a file being picked anywhere on the page.
     *
     * The file manager opens its upload control inside a modal that is created on demand, so the
     * listener is delegated from the document rather than bound to a particular input. That keeps
     * it working across the Moodle versions this plugin supports, whose file picker markup differs.
     *
     * @param {Event} event the change event
     * @return {void}
     */
    var handleChange = function(event) {
        var target = event.target;
        if (!target || target.type !== 'file' || !target.files || !target.files.length) {
            return;
        }

        if (target.files.length > config.maxfiles) {
            render('', [{
                key: 'issue_toomanyfiles',
                param: {count: target.files.length, max: config.maxfiles}
            }]);
            return;
        }

        var file = target.files[0];
        renderChecking();
        inspect(file).then(function(problems) {
            return render(file.name, problems);
        }).catch(function(error) {
            Log.debug('mod_pagecheck validation failed: ' + error);
        });
    };

    return {
        /**
         * Start watching the file picker.
         *
         * @param {Object} settings the activity restrictions, from edit.php
         * @return {void}
         */
        init: function(settings) {
            config = settings;
            config.allowedextensions = config.allowedextensions || [];

            if (typeof TextDecoder === 'undefined' || typeof Promise === 'undefined') {
                // An old browser simply gets the server side checks, which is all it ever needed.
                return;
            }

            document.addEventListener('change', handleChange, true);
        }
    };
});
