/* callHome.c - sends usage data for GenomeVIP
	author: Adam D Scott
	first created: 2015*10*09
	derived from: https://github.com/bagder/curl/blob/master/docs/examples/http-post.c
*/
#include <stdio.h>
#include <curl/curl.h>

int main(void) {
	CURL *curl;
	CURLcode res;

	curl_global_init( CURL_GLOBAL_ALL );
	curl = curl_easy_init();
	if ( curl ) {
		char aws[] = "test.html";
		char message[] = "usageData.xml";
		curl_easy_setopt( curl , CURLOPT_URL , aws );
		curl_easy_setopt( curl , CURLOPT_POSTFIELDS , message );

		res = curl_easy_perform( curl );
		if ( res != CURLE_OK ) {
			fprintf( stderr ,
				"curl_easy_perform() failed: %s\n" ,
				curl_easy_strerror( res )
			);
			curl_easy_cleanup( curl );
		}
	}
	curl_global_cleanup();
	return 0;
}
