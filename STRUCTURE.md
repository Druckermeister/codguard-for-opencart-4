# CodGuard OpenCart - Repository Structure

Last updated: December 19, 2024

## 📁 Root Directory

```
codguard-for-opencart/
├── README.md                          # Main documentation
├── CREDENTIALS.md                     # Access credentials and configuration
├── .env                               # Environment variables
├── LICENSE.txt                        # License information
├── CHANGELOG.md                       # Complete version history
├── STRUCTURE.md                       # This file - directory structure
├── codguard-oc4-v2.9.0.ocmod.zip     # Latest release (v2.9.0)
├── codguard-oc4/                      # Source code directory
├── upload/                            # Installation files
├── docs/                              # Documentation files
├── scripts/                           # SQL and utility scripts
└── archive/                           # Old versions and deprecated files
```

## 📚 /docs/

Current documentation and guides:

- **FEATURES.md** - Detailed feature descriptions
- **TROUBLESHOOTING.md** - Common issues and solutions
- **CRON_SETUP.md** - Cron job setup instructions
- **INSTALL_QUICK_v2.9.0.md** - Quick installation guide (current)
- **QUICKSTART.md** - Quick start guide
- **UPGRADE-TO-OC4.md** - Migration guide to OpenCart 4
- **PROJECT_SUMMARY.md** - Project overview
- **IMPLEMENTATION_SUMMARY.md** - Technical implementation details
- **PAYMENT_FILTER_IMPLEMENTATION.md** - Payment filter feature details

## 🗄️ /archive/old-versions/

Deprecated files from previous versions:

- **codguard-oc4-v2.5.4.ocmod.zip** - Old release
- **codguard-oc4-v2.5.5.ocmod.zip** - Old release
- **INSTALL_v2.2.2.md** - Old installation guide
- **CODE_ANALYSIS_v2.5.3.md** - Old code analysis
- **VERSION_2.5.3_SUMMARY.md** - Version 2.5.3 notes
- **VERSION_2.5.4_SUMMARY.md** - Version 2.5.4 notes
- **RELEASE_NOTES_v2.5.3.md** - Release notes for 2.5.3

## 🔧 /scripts/

Database and utility scripts:

- **fix-permissions-oc4.sql** - Fix user permissions for OC4
- **fix-permissions.sql** - Fix user permissions (general)

## 💻 /codguard-oc4/

Source code for the extension (development files)

## 📦 /upload/

Installation files ready to be uploaded to OpenCart

---

## Quick Navigation

### For Installation
1. Start with: **README.md**
2. Then read: **docs/INSTALL_QUICK_v2.9.0.md**
3. Download: **codguard-oc4-v2.9.0.ocmod.zip**

### For Configuration
1. Check: **CREDENTIALS.md** for access details
2. Read: **docs/CRON_SETUP.md** for cron configuration
3. See: **docs/FEATURES.md** for feature details

### For Troubleshooting
1. Check: **docs/TROUBLESHOOTING.md**
2. Review: **CHANGELOG.md** for known issues
3. Inspect: **scripts/** for database fixes

### For Development
1. Source code: **codguard-oc4/**
2. Installation files: **upload/**
3. Implementation details: **docs/IMPLEMENTATION_SUMMARY.md**

---

## Version Information

**Current Version:** 2.9.0
**OpenCart Compatibility:** 4.x
**Last Updated:** November 29, 2024

For older versions, see: **archive/old-versions/**
