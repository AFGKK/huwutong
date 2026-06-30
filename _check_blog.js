const fs = require('fs');
const https = require('https');

// Fetch the blog page and extract the main script
https.get('http://88.huwutong.com/blog', (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
        // Find the main script (the one with loadPosts)
        const match = data.match(/<script>\n    const API = '\/api\/public';\n[\s\S]*?function downloadPoster\(\) \{[\s\S]*?a\.click\(\);\n    \}\n<\/script>/);
        if (match) {
            const script = match[0].replace(/<\/?script>/g, '');
            fs.writeFileSync('blog_script.js', script);
            console.log('Script saved, length:', script.length);
            
            // Try to parse it
            try {
                new Function(script);
                console.log('NO SYNTAX ERRORS');
            } catch(e) {
                console.log('SYNTAX ERROR:', e.message);
                // Try to find the error line
                const lines = script.split('\n');
                for (let i = 0; i < lines.length; i++) {
                    try {
                        new Function(lines.slice(0, i + 1).join('\n'));
                    } catch(e2) {
                        console.log(`Error around line ${i+1}: ${e2.message}`);
                        console.log(`Line ${i+1}: ${lines[i]?.substring(0, 100)}`);
                        break;
                    }
                }
            }
        } else {
            console.log('Script not found');
            // Try to find script tags
            const scripts = data.match(/<script>[\s\S]*?<\/script>/g);
            console.log('Found', scripts?.length, 'inline scripts');
        }
    });
}).on('error', e => console.error('Error:', e.message));
