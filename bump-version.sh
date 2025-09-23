#!/bin/bash

# Version Bump Script for Choice Universal Form Tracker
# Usage: ./bump-version.sh <new_version>
# Example: ./bump-version.sh 3.8.5

if [ -z "$1" ]; then
    echo "Error: Please provide a version number"
    echo "Usage: $0 <new_version>"
    echo "Example: $0 3.8.5"
    exit 1
fi

NEW_VERSION=$1
OLD_VERSION=$(grep "Version:" choice-universal-form-tracker.php | head -1 | sed 's/.*Version: *//' | sed 's/ *$//')

echo "Updating version from $OLD_VERSION to $NEW_VERSION"

# Update plugin header
sed -i "s/Version:           $OLD_VERSION/Version:           $NEW_VERSION/" choice-universal-form-tracker.php
echo "✓ Updated plugin header"

# Update CUFT_VERSION constant
sed -i "s/define( 'CUFT_VERSION', '$OLD_VERSION' )/define( 'CUFT_VERSION', '$NEW_VERSION' )/" choice-universal-form-tracker.php
echo "✓ Updated CUFT_VERSION constant"

# Update readme.txt stable tag
sed -i "s/Stable tag: $OLD_VERSION/Stable tag: $NEW_VERSION/" readme.txt
echo "✓ Updated readme.txt"

# Add entry to CHANGELOG.md (at line 8, after the header)
DATE=$(date +%Y-%m-%d)
sed -i "8i\\
## [$NEW_VERSION] - $DATE\\
\\
### Changed\\
\\
- Version bump to $NEW_VERSION\\
" CHANGELOG.md
echo "✓ Added CHANGELOG.md entry"

echo ""
echo "Version updated to $NEW_VERSION successfully!"
echo ""
echo "Next steps:"
echo "1. Update CHANGELOG.md with actual changes for this version"
echo "2. Commit changes: git add -A && git commit -m \"Release version $NEW_VERSION\""
echo "3. Create tag: git tag v$NEW_VERSION"
echo "4. Push changes: git push && git push --tags"
echo ""
echo "The GitHub Actions workflow will automatically create the release and plugin zip."