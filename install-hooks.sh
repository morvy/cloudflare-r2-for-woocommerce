#!/bin/bash

# Install Git hooks for Cloudflare R2 for WooCommerce

set +e

echo "Installing Git hooks..."

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "❌ Error: Not in a git repository"
    exit 1
fi

# Create hooks directory if it doesn't exist
mkdir -p .git/hooks

# Copy pre-commit hook
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash

# Cloudflare R2 for WooCommerce - Pre-commit Hook
# Runs code quality checks before allowing commit

set +e

echo "🔍 Running pre-commit quality checks..."
echo ""

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Not in plugin root directory"
    exit 1
fi

# Check if composer vendor exists
if [ ! -d "vendor" ]; then
    echo "❌ Error: Composer dependencies not installed. Run: composer install"
    exit 1
fi

# Track if any check fails
CHECKS_FAILED=0

# 1. PHP CodeSniffer (WordPress Coding Standards)
echo "📝 Running PHP CodeSniffer..."
# Only fail on errors (exit code 2), not warnings (exit code 1)
composer run phpcs --quiet 2>&1 > /dev/null
PHPCS_EXIT=$?
if [ $PHPCS_EXIT -eq 0 ]; then
    echo "✅ PHP CodeSniffer passed"
elif [ $PHPCS_EXIT -eq 1 ]; then
    echo "⚠️  PHP CodeSniffer warnings (acceptable)"
else
    echo "❌ PHP CodeSniffer failed with errors"
    echo "   Fix with: composer run phpcbf"
    CHECKS_FAILED=1
fi
echo ""

# 2. PHPStan (Static Analysis)
echo "🔬 Running PHPStan..."
if composer run phpstan --quiet 2>&1 > /dev/null; then
    echo "✅ PHPStan passed"
else
    echo "❌ PHPStan failed"
    echo "   Review errors above"
    CHECKS_FAILED=1
fi
echo ""

# 3. Rector (Dry Run)
echo "🔧 Running Rector..."
if composer run rector --quiet 2>&1 > /dev/null; then
    echo "✅ Rector passed"
else
    echo "❌ Rector found issues"
    echo "   Auto-fix with: composer run rector-fix"
    CHECKS_FAILED=1
fi
echo ""

# 4. PHPUnit Tests
echo "🧪 Running PHPUnit tests..."
if composer run test --quiet 2>&1 > /dev/null; then
    echo "✅ Tests passed"
else
    echo "❌ Tests failed"
    echo "   Review test failures above"
    CHECKS_FAILED=1
fi
echo ""

# Final result
if [ $CHECKS_FAILED -eq 1 ]; then
    echo "❌ Pre-commit checks failed!"
    echo ""
    echo "To bypass this check (not recommended):"
    echo "  git commit --no-verify"
    echo ""
    exit 1
else
    echo "✅ All checks passed! Proceeding with commit..."
    echo ""
    exit 0
fi
EOF

# Make hook executable
chmod +x .git/hooks/pre-commit

echo "✅ Git hooks installed successfully!"
echo ""
echo "Pre-commit hook will now run:"
echo "  - PHP CodeSniffer"
echo "  - PHPStan"
echo "  - Rector"
echo "  - PHPUnit Tests"
echo ""
echo "To bypass (not recommended): git commit --no-verify"
