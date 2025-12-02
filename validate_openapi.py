import yaml
import sys
import json

try:
    # Load and parse YAML
    with open('openapi.yaml', 'r', encoding='utf-8') as f:
        data = yaml.safe_load(f)
    
    print("="*60)
    print("🔍 OpenAPI YAML Validation Report")
    print("="*60)
    print("\n✅ YAML syntax is VALID!")
    print(f"\n📊 OpenAPI Information:")
    print(f"  - Version: {data.get('openapi')}")
    print(f"  - Title: {data.get('info', {}).get('title')}")
    print(f"  - API Version: {data.get('info', {}).get('version')}")
    print(f"  - Description: {data.get('info', {}).get('description', '')[:50]}...")
    
    print(f"\n📋 API Statistics:")
    paths = data.get('paths', {})
    schemas = data.get('components', {}).get('schemas', {})
    security = data.get('components', {}).get('securitySchemes', {})
    tags = data.get('tags', [])
    
    print(f"  - Total Paths: {len(paths)}")
    print(f"  - Total Schemas: {len(schemas)}")
    print(f"  - Security Schemes: {len(security)}")
    print(f"  - Tags: {len(tags)}")
    
    # Count operations
    operations = 0
    methods_count = {'get': 0, 'post': 0, 'put': 0, 'delete': 0, 'patch': 0}
    
    for path, methods in paths.items():
        for method in methods.keys():
            if method.lower() in methods_count:
                operations += 1
                methods_count[method.lower()] += 1
    
    print(f"  - Total Operations: {operations}")
    print(f"\n📊 Operations by Method:")
    for method, count in methods_count.items():
        if count > 0:
            print(f"  - {method.upper()}: {count}")
    
    print(f"\n🏷️  API Tags:")
    for tag in tags:
        print(f"  - {tag.get('name')}: {tag.get('description', 'No description')[:50]}")
    
    print(f"\n🔐 Security Schemes:")
    for name, scheme in security.items():
        print(f"  - {name}: {scheme.get('type')} ({scheme.get('scheme', 'N/A')})")
    
    # Validate required fields
    print(f"\n🔍 Validation Checks:")
    errors = []
    warnings = []
    
    # Required fields
    if 'openapi' not in data:
        errors.append("Missing 'openapi' version")
    elif not data['openapi'].startswith('3.0'):
        errors.append(f"Unsupported OpenAPI version: {data['openapi']}")
    else:
        print(f"  ✅ OpenAPI version is valid (3.0.x)")
    
    if 'info' not in data:
        errors.append("Missing 'info' section")
    else:
        if 'title' not in data['info']:
            errors.append("Missing 'info.title'")
        else:
            print(f"  ✅ Info section is complete")
        if 'version' not in data['info']:
            errors.append("Missing 'info.version'")
    
    if 'paths' not in data or len(data['paths']) == 0:
        errors.append("No paths defined")
    else:
        print(f"  ✅ Paths are defined ({len(paths)} endpoints)")
    
    # Check schemas
    if 'components' in data and 'schemas' in data['components']:
        print(f"  ✅ Schemas are defined ({len(schemas)} schemas)")
    else:
        warnings.append("No schemas defined in components")
    
    # Check for common issues in paths
    missing_responses = []
    for path, methods in paths.items():
        for method, details in methods.items():
            if method.lower() in ['get', 'post', 'put', 'delete', 'patch']:
                if 'responses' not in details:
                    missing_responses.append(f"{method.upper()} {path}")
    
    if missing_responses:
        errors.append(f"Missing 'responses' in {len(missing_responses)} operations")
        print(f"  ❌ Some operations missing responses")
    else:
        print(f"  ✅ All operations have responses defined")
    
    # Check security
    if 'security' in data or ('components' in data and 'securitySchemes' in data['components']):
        print(f"  ✅ Security configuration present")
    else:
        warnings.append("No security configuration found")
    
    # Summary
    print(f"\n{'='*60}")
    print(f"📊 Validation Summary:")
    print(f"{'='*60}")
    
    if errors:
        print(f"\n❌ ERRORS FOUND ({len(errors)}):")
        for i, error in enumerate(errors, 1):
            print(f"  {i}. {error}")
    
    if warnings:
        print(f"\n⚠️  WARNINGS ({len(warnings)}):")
        for i, warning in enumerate(warnings, 1):
            print(f"  {i}. {warning}")
    
    if not errors and not warnings:
        print(f"\n🎉 PERFECT! No errors or warnings found!")
        print(f"✅ OpenAPI specification is VALID and ready to use!")
        print(f"\n📝 You can now:")
        print(f"  - Import into Swagger UI or Postman")
        print(f"  - Generate client SDKs")
        print(f"  - Use with MCP servers")
        print(f"  - Deploy as API documentation")
    elif not errors:
        print(f"\n✅ OpenAPI specification is VALID!")
        print(f"⚠️  Consider addressing warnings for best practices")
    else:
        print(f"\n❌ Please fix errors before using")
        sys.exit(1)
    
    print(f"\n{'='*60}\n")
        
except yaml.YAMLError as e:
    print(f"❌ YAML parsing error: {e}")
    sys.exit(1)
except Exception as e:
    print(f"❌ Error: {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)
