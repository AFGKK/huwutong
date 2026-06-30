// HWT License Go SDK
//
// Usage:
//
//	package main
//
//	import (
//	    "log"
//	    "github.com/huwutong/huwutong-sdk-go/huwutong"
//	)
//
//	func main() {
//	    client := huwutong.NewClient("your_api_key", "https://api.huwutong.com")
//
//	    result, err := client.Activate("LICENSE-KEY", map[string]interface{}{
//	        "machine_id": "unique-machine-id",
//	        "hostname":   "server-01",
//	    })
//	    if err != nil {
//	        log.Fatalf("激活失败: %v", err)
//	    }
//	    log.Printf("激活成功, 到期: %s", result.ExpiresAt)
//	}
package huwutong
