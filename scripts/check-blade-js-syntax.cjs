#!/usr/bin/env node

/**
 * UrbanPOS Automated Blade JavaScript Syntax Checker
 * 
 * Scans all Blade templates in resources/views for inline <script> blocks,
 * extracts them, sanitizes Blade templating directives, and runs Node.js syntax analysis.
 * 
 * Fails with exit code 1 if ANY syntax error (e.g. unexpected token, unclosed bracket)
 * is found, preventing broken JavaScript from ever being pushed to git or deployed.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const VIEWS_DIR = path.resolve(__dirname, '..', 'resources', 'views');

function getAllBladeFiles(dir, fileList = []) {
    if (!fs.existsSync(dir)) return fileList;
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
            getAllBladeFiles(fullPath, fileList);
        } else if (file.endsWith('.blade.php')) {
            fileList.push(fullPath);
        }
    }
    return fileList;
}

function cleanBladeJs(rawJs) {
    let js = rawJs;

    // 1. Remove Blade comments {{-- ... --}}
    js = js.replace(/\{\{--[\s\S]*?--\}\}/g, '');

    // 2. Replace Blade @json(...) directives
    js = js.replace(/@json\([\s\S]*?\);/g, '{};');
    js = js.replace(/@json\([\s\S]*?\)/g, '{}');

    // 3. Replace @foreach, @forelse, @while, @if, @else, @endif, @endforeach, etc.
    js = js.replace(/^[ \t]*@(?:foreach|forelse|while)\s*\([^\n]*\)[ \t]*$/gm, '{');
    js = js.replace(/^[ \t]*@(?:endforeach|endforelse|endwhile)[ \t]*$/gm, '}');
    js = js.replace(/^[ \t]*@(?:if|unless|isset|empty)\s*\([^\n]*\)[ \t]*$/gm, 'if (true) {');
    js = js.replace(/^[ \t]*@(?:elseif)\s*\([^\n]*\)[ \t]*$/gm, '} else if (true) {');
    js = js.replace(/^[ \t]*@(?:else)[ \t]*$/gm, '} else {');
    js = js.replace(/^[ \t]*@(?:endif|endunless|endisset|endempty)[ \t]*$/gm, '}');
    js = js.replace(/^[ \t]*@(?:php)[\s\S]*?@(?:endphp)[ \t]*$/gm, '');
    js = js.replace(/^[ \t]*@(?:push|endpush|section|endsection|stop|once|endonce)[^\n]*$/gm, '');

    // 4. Token-aware replacement of {{ ... }} and {!! ... !!}
    // We walk the string character by character tracking whether we are inside '', "", or ``
    let out = '';
    let inSingle = false;
    let inDouble = false;
    let inBacktick = false;
    let i = 0;

    while (i < js.length) {
        // Check for {{ ... }} or {!! ... !!}
        if (js.startsWith('{{', i) || js.startsWith('{!!', i)) {
            const isRaw = js.startsWith('{!!', i);
            const closeTag = isRaw ? '!!}' : '}}';
            const closeIdx = js.indexOf(closeTag, i + 2);
            if (closeIdx !== -1) {
                // If inside any string literal ('', "", or ``), insert dummy text safe for that string
                if (inSingle || inDouble || inBacktick) {
                    out += 'dummy';
                } else {
                    // Outside quotes: could be in expression, assignment, or parameter
                    out += '1';
                }
                i = closeIdx + closeTag.length;
                continue;
            }
        }

        const ch = js[i];
        const prev = i > 0 ? js[i - 1] : '';

        // Handle escape character
        if (prev !== '\\') {
            if (ch === '\'' && !inDouble && !inBacktick) {
                inSingle = !inSingle;
            } else if (ch === '"' && !inSingle && !inBacktick) {
                inDouble = !inDouble;
            } else if (ch === '`' && !inSingle && !inDouble) {
                inBacktick = !inBacktick;
            }
        }

        out += ch;
        i++;
    }

    return out;
}

function checkBladeFiles() {
    console.log('==========================================================');
    console.log(' 🛡️  Scanning Blade templates for JavaScript syntax errors...');
    console.log('==========================================================');

    const bladeFiles = getAllBladeFiles(VIEWS_DIR);
    let totalScripts = 0;
    let errorsFound = 0;

    const scriptRegex = /<script(?:\s+[^>]*)?>([\s\S]*?)<\/script>/gi;

    for (const filePath of bladeFiles) {
        const content = fs.readFileSync(filePath, 'utf8');
        let match;
        let scriptIndex = 0;

        while ((match = scriptRegex.exec(content)) !== null) {
            const rawTag = match[0];
            // Skip application/json or external src scripts without inline code
            if (rawTag.includes('type="application/json"') || rawTag.includes("type='application/json'")) {
                continue;
            }
            const js = match[1].trim();
            if (!js) continue;

            totalScripts++;
            scriptIndex++;

            const upToMatch = content.substring(0, match.index);
            const scriptStartLine = upToMatch.split('\n').length;

            const cleaned = cleanBladeJs(js);
            const tempFile = path.resolve(__dirname, `__tmp_syntax_${process.pid}_${scriptIndex}.js`);

            try {
                fs.writeFileSync(tempFile, cleaned);
                execSync(`node --check "${tempFile}"`, { stdio: 'pipe' });
            } catch (err) {
                errorsFound++;
                const relativePath = path.relative(path.resolve(__dirname, '..'), filePath);
                console.error(`\n❌ SYNTAX ERROR DETECTED:`);
                console.error(`   File: ${relativePath} (near line ${scriptStartLine})`);
                const errMsg = err.stderr ? err.stderr.toString().trim() : err.message;
                console.error(`   Details:\n${errMsg}\n`);
            } finally {
                if (fs.existsSync(tempFile)) {
                    fs.unlinkSync(tempFile);
                }
            }
        }
    }

    console.log(`Scanned ${bladeFiles.length} blade templates (${totalScripts} inline script blocks).`);

    if (errorsFound > 0) {
        console.error(`\n❌ FAILED: ${errorsFound} JavaScript syntax error(s) found in Blade views!`);
        console.error('Please fix the syntax error(s) above before pushing or deploying.\n');
        process.exit(1);
    } else {
        console.log('✅ All inline Blade JavaScript blocks passed syntax check (0 errors).\n');
        process.exit(0);
    }
}

checkBladeFiles();
